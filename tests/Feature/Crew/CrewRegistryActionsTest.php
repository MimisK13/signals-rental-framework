<?php

declare(strict_types=1);

use App\Domain\Crew\Actions\Registry\AddCrewMemberQualification;
use App\Domain\Crew\Actions\Registry\ArchiveCrewMember;
use App\Domain\Crew\Actions\Registry\CreateCrewMember;
use App\Domain\Crew\Actions\Registry\CreateCrewRole;
use App\Domain\Crew\Actions\Registry\CreateCrewSkill;
use App\Domain\Crew\Actions\Registry\SyncCrewMemberRoles;
use App\Domain\Crew\Actions\Registry\SyncCrewMemberSkills;
use App\Domain\Crew\Actions\Registry\VerifyCrewMemberQualification;
use App\Domain\Crew\Data\Registry\AddCrewMemberQualificationData;
use App\Domain\Crew\Data\Registry\CreateCrewMemberData;
use App\Domain\Crew\Data\Registry\CreateCrewRoleData;
use App\Domain\Crew\Data\Registry\CreateCrewSkillData;
use App\Domain\Crew\Data\Registry\CrewMemberData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberRolesData;
use App\Domain\Crew\Data\Registry\SyncCrewMemberSkillsData;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewMemberQualification;
use App\Domain\Crew\Models\CrewQualification;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Crew\Models\CrewSkill;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

it('creates a person without a login and records an atomic audit', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));

    $data = app(CreateCrewMember::class)(new CreateCrewMemberData('  Alex Crew  ', 'freelancer', 'alex@example.com'));

    expect($data->name)->toBe('Alex Crew')->and($data->user_id)->toBeNull();
    $this->assertDatabaseHas('crew_members', ['id' => $data->id, 'name' => 'Alex Crew', 'engagement_type' => 'freelancer']);
    $this->assertDatabaseHas('action_logs', ['auditable_id' => $data->id, 'action' => 'crew.member.created']);
});

it('rejects invalid direct action input without writing a person or audit', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));

    expect(function (): void {
        app(CreateCrewMember::class)(new CreateCrewMemberData('   ', email: 'invalid'));
    })->toThrow(ValidationException::class);

    $this->assertDatabaseCount('crew_members', 0);
    $this->assertDatabaseMissing('action_logs', ['action' => 'crew.member.created']);
});

it('denies registry writes without permission', function (): void {
    $this->actingAs(User::factory()->create());

    expect(function (): void {
        app(CreateCrewMember::class)(new CreateCrewMemberData('Alex'));
    })->toThrow(AuthorizationException::class);
    $this->assertDatabaseCount('crew_members', 0);
});

it('redacts personal contact data and internal notes for ordinary viewers', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('crew.view');
    $this->actingAs($user);
    $member = CrewMember::factory()->create(['internal_notes' => 'Private', 'email' => 'private@example.com']);

    $data = CrewMemberData::fromModel($member);

    expect($data->email)->toBeNull()->and($data->phone)->toBeNull()->and($data->internal_notes)->toBeNull();
    $user->givePermissionTo('crew.view.sensitive');
    expect(CrewMemberData::fromModel($member)->internal_notes)->toBe('Private');
});

it('creates reusable role and skill vocabulary with unique normalized slugs', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));

    $role = app(CreateCrewRole::class)(new CreateCrewRoleData('Sound Engineer'));
    $skill = app(CreateCrewSkill::class)(new CreateCrewSkillData('Grand MA3'));

    expect($role->slug)->toBe('sound-engineer')->and($skill->slug)->toBe('grand-ma3');
    expect(function (): void {
        app(CreateCrewSkill::class)(new CreateCrewSkillData('Grand MA3'));
    })->toThrow(ValidationException::class);
    $this->assertDatabaseCount('crew_skills', 1);
});

it('syncs member skills transactionally and rejects duplicate skill identifiers', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));
    $member = CrewMember::factory()->create();
    $skill = CrewSkill::factory()->create();

    app(SyncCrewMemberSkills::class)($member, new SyncCrewMemberSkillsData([
        ['crew_skill_id' => $skill->id, 'proficiency' => 'expert', 'years_experience' => 5],
    ]));

    $this->assertDatabaseHas('crew_member_skills', ['crew_member_id' => $member->id, 'crew_skill_id' => $skill->id, 'proficiency' => 'expert']);
    expect(function () use ($member, $skill): void {
        app(SyncCrewMemberSkills::class)($member, new SyncCrewMemberSkillsData([
            ['crew_skill_id' => $skill->id, 'proficiency' => 'competent'],
            ['crew_skill_id' => $skill->id, 'proficiency' => 'trainee'],
        ]));
    })->toThrow(ValidationException::class);
    $this->assertDatabaseHas('crew_member_skills', ['crew_member_id' => $member->id, 'proficiency' => 'expert']);
});

it('requires exactly one primary role when replacing member roles', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));
    $member = CrewMember::factory()->create();
    $role = CrewRole::factory()->create();

    expect(function () use ($member, $role): void {
        app(SyncCrewMemberRoles::class)($member, new SyncCrewMemberRolesData([
            ['crew_role_id' => $role->id, 'proficiency' => 'competent', 'is_primary' => false],
        ]));
    })->toThrow(ValidationException::class);
    $this->assertDatabaseCount('crew_member_roles', 0);
});

it('requires an expiry for a certificate and records its verifier', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
    $user = User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    })));
    $this->actingAs($user);
    $member = CrewMember::factory()->create();
    $qualification = CrewQualification::factory()->create(['requires_expiry' => true]);

    expect(function () use ($member, $qualification): void {
        app(AddCrewMemberQualification::class)($member, new AddCrewMemberQualificationData($qualification->id));
    })->toThrow(ValidationException::class);

    $data = app(AddCrewMemberQualification::class)($member, new AddCrewMemberQualificationData($qualification->id, '2026-01-01', '2027-01-01'));
    $verified = app(VerifyCrewMemberQualification::class)(CrewMemberQualification::findOrFail($data->id));

    expect($verified->status)->toBe('valid');
    $this->assertDatabaseHas('crew_member_qualifications', ['id' => $data->id, 'verified_by' => $user->id]);
});

it('archives crew without deleting the historical person', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true])->givePermissionTo(array_keys(array_filter(PermissionSeeder::permissions(), function (array $meta): bool {
        return $meta['group'] === 'Crew';
    }))));
    $member = CrewMember::factory()->create();

    app(ArchiveCrewMember::class)($member);

    $this->assertSoftDeleted($member);
    $this->assertDatabaseHas('crew_members', ['id' => $member->id, 'status' => 'archived']);
});

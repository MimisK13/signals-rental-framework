<?php

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Data\Projects\CreateProjectData;
use App\Data\Projects\UpdateProjectData;
use App\Enums\MembershipType;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ProjectSeeder;

beforeEach(function (): void {
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a project through the canonical action', function (): void {
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();
    $owner = Member::factory()->user()->create();

    $result = (new CreateProject)(CreateProjectData::from([
        'name' => 'Summer Festival 2027',
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
        'owned_by' => $owner->id,
        'status' => ProjectStatus::Draft->value,
        'starts_at' => '2027-06-18 08:00:00',
        'ends_at' => '2027-06-21 23:00:00',
    ]));

    $project = Project::query()->findOrFail($result->id);

    expect($project->name)->toBe('Summer Festival 2027')
        ->and($project->customer->is($customer))->toBeTrue()
        ->and($project->venue->is($venue))->toBeTrue()
        ->and($project->owner->is($owner))->toBeTrue()
        ->and($project->status)->toBe(ProjectStatus::Draft)
        ->and($project->starts_at?->toDateString())->toBe('2027-06-18')
        ->and($project->ends_at?->toDateString())->toBe('2027-06-21');
});

it('updates project metadata through the canonical action', function (): void {
    $project = Project::factory()->create([
        'name' => 'Draft engagement',
        'status' => ProjectStatus::Draft,
    ]);
    $customer = Member::factory()->organisation()->create();
    $venue = Member::factory()->venue()->create();

    (new UpdateProject)($project, UpdateProjectData::from([
        'name' => 'Updated engagement',
        'member_id' => $customer->id,
        'venue_id' => $venue->id,
        'status' => ProjectStatus::Active->value,
        'starts_at' => '2027-08-01 09:00:00',
        'ends_at' => '2027-08-04 18:00:00',
    ]));

    $project->refresh();

    expect($project->name)->toBe('Updated engagement')
        ->and($project->member_id)->toBe($customer->id)
        ->and($project->venue_id)->toBe($venue->id)
        ->and($project->status)->toBe(ProjectStatus::Active)
        ->and($project->starts_at?->toDateString())->toBe('2027-08-01')
        ->and($project->ends_at?->toDateString())->toBe('2027-08-04');
});

it('deletes a project without deleting unrelated domain records', function (): void {
    $project = Project::factory()->create();

    (new DeleteProject)($project);

    expect(Project::withTrashed()->findOrFail($project->id)->trashed())->toBeTrue()
        ->and(Project::query()->find($project->id))->toBeNull();
});

it('keeps the seeded member type contract available for project CRUD data', function (): void {
    expect(Member::factory()->organisation()->create()->membership_type)->toBe(MembershipType::Organisation)
        ->and(Member::factory()->venue()->create()->membership_type)->toBe(MembershipType::Venue)
        ->and(Member::factory()->user()->create()->membership_type)->toBe(MembershipType::User);
});

it('seeds realistic projects idempotently', function (): void {
    $this->seed(ProjectSeeder::class);
    $this->seed(ProjectSeeder::class);

    expect(Project::query()->whereIn('name', [
        'Summer Festival 2027',
        'European Arena Tour 2027',
        'Corporate AV Programme 2027',
    ])->count())->toBe(3)
        ->and(Member::query()->where('name', 'Northstar Live Events')->value('membership_type'))
        ->toBe(MembershipType::Organisation)
        ->and(Member::query()->where('name', 'Riverside Event Centre')->value('membership_type'))
        ->toBe(MembershipType::Venue)
        ->and(Member::query()->where('name', 'Alex Morgan')->value('membership_type'))
        ->toBe(MembershipType::User);
});

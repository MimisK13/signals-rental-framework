<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\API\Services\AbilityRegistry;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Crew\Actions\Registry\CreateCrewMember;
use App\Domain\Crew\Data\Registry\CreateCrewMemberData;
use App\Domain\Crew\Events\CrewDomainEvent;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewEventDefinitions;
use App\Domain\Notifications\Services\EventNotificationMapRegistry;
use App\Domain\Notifications\Services\NotificationRegistry;
use App\Domain\Projects\Models\Project;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('registers crew token abilities webhook names and notification mappings', function (): void {
    expect(app(AbilityRegistry::class)->get('crew:read'))->not->toBeNull();
    foreach (CrewEventDefinitions::WEBHOOKS as $name) {
        expect(app(WebhookEventRegistry::class)->has($name))->toBeTrue();
    }
    foreach (CrewEventDefinitions::NOTIFICATIONS as $name) {
        expect(app(NotificationRegistry::class)->has($name))->toBeTrue();
        expect(app(EventNotificationMapRegistry::class)->has($name))->toBeTrue();
    }
});

it('rolls back the domain write when the atomic audit fails', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('crew.create');
    $this->actingAs($user);
    Event::listen(AuditableEvent::class, function (AuditableEvent $event): void {
        if ($event->action === 'crew.member.created') {
            throw new RuntimeException('Audit unavailable');
        }
    });

    expect(function (): void {
        app(CreateCrewMember::class)(new CreateCrewMemberData('Atomic Crew'));
    })->toThrow(RuntimeException::class, 'Audit unavailable');

    $this->assertDatabaseMissing('crew_members', ['name' => 'Atomic Crew']);
    $this->assertDatabaseMissing('action_logs', ['action' => 'crew.member.created']);
});

it('does not dispatch crew events from a rolled back transaction', function (): void {
    $member = CrewMember::factory()->create();
    Event::fake([CrewDomainEvent::class]);

    expect(function () use ($member): void {
        DB::transaction(function () use ($member): void {
            CrewAudit::record('crew.member.updated', $member);
            throw new RuntimeException('Roll back');
        });
    })->toThrow(RuntimeException::class);

    Event::assertNotDispatched(CrewDomainEvent::class);
    $this->assertDatabaseMissing('action_logs', ['action' => 'crew.member.updated']);
});

it('flags confirmed crew for review without moving shifts when a project schedule changes', function (): void {
    $this->freezeTime();
    $project = Project::factory()->create(['starts_at' => '2026-11-02 09:00:00', 'ends_at' => '2026-11-02 18:00:00']);
    $assignment = CrewAssignment::factory()->confirmed()->create(['project_id' => $project->id]);
    $shift = CrewShift::factory()->forAssignment($assignment)->create(['starts_at' => '2026-11-02 09:00:00', 'ends_at' => '2026-11-02 18:00:00']);

    $project->update(['starts_at' => '2026-11-03 09:00:00', 'ends_at' => '2026-11-03 18:00:00']);

    expect($assignment->fresh()->requires_realignment)->toBeTrue();
    expect($shift->fresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-11-02 09:00:00');
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.assignment.realignment_required', 'auditable_id' => $assignment->id]);
});

it('rejects mutation of agreed assignment compensation', function (): void {
    $assignment = CrewAssignment::factory()->confirmed()->create(['agreed_at' => now(), 'rate_amount_minor' => 4500]);

    expect(function () use ($assignment): void {
        $assignment->update(['rate_amount_minor' => 9000]);
    })->toThrow(ValidationException::class);

    $this->assertDatabaseHas('crew_assignments', ['id' => $assignment->id, 'rate_amount_minor' => 4500]);
});

it('anonymises retained archived contact data without deleting crew financial history', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
    settings()->set('modules.crew', true, 'boolean');
    $member = CrewMember::factory()->create(['status' => 'archived', 'email' => 'retired@example.test', 'internal_notes' => 'Personal notes']);
    $assignment = CrewAssignment::factory()->forMember($member)->cancelled()->create();
    $member->delete();
    $member->forceFill(['deleted_at' => now()->subMonths(25)])->save();

    $this->artisan('crew:anonymise-expired-contacts')->assertSuccessful();

    $this->assertDatabaseHas('crew_members', ['id' => $member->id, 'name' => 'Archived crew #'.$member->id, 'email' => null, 'internal_notes' => null]);
    $this->assertModelExists($assignment);
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.member.anonymised', 'auditable_id' => $member->id]);
});

it('exports the Crew API schema against the migrated test database', function (): void {
    $path = storage_path('framework/testing/crew-openapi.json');
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    try {
        $this->artisan('scramble:export', [
            '--routes' => 'api.v1.crew.members.index,api.v1.crew.members.store,api.v1.crew.requests.index,api.v1.crew.assignments.confirm,api.v1.crew.timesheets.approve',
            '--path' => $path, '--fail-on-unknown' => true,
        ])->assertSuccessful();
        $schema = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        expect($schema['paths'])->toHaveKey('/v1/crew/members');
        expect($schema['paths']['/v1/crew/members'])->toHaveKeys(['get', 'post']);
        $memberInput = $schema['paths']['/v1/crew/members']['post']['requestBody']['content']['application/json']['schema'];
        if (isset($memberInput['$ref'])) {
            $memberInput = $schema['components']['schemas'][basename($memberInput['$ref'])];
        }
        expect($memberInput['properties'])->toHaveKeys(['name', 'engagement_type', 'email']);
        expect($memberInput['required'])->toContain('name');
        expect($schema['paths'])->toHaveKey('/v1/crew/assignments/{crewAssignment}/confirm');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('can roll back and recreate every Crew table without changing other domains', function (): void {
    $paths = glob(app_path('Domain/Crew/Database/Migrations/*.php'));
    sort($paths);
    foreach (array_reverse($paths) as $path) {
        (require $path)->down();
    }
    expect(Schema::hasTable('crew_members'))->toBeFalse();
    expect(Schema::hasTable('projects'))->toBeTrue();
    foreach ($paths as $path) {
        (require $path)->up();
    }
    expect(Schema::hasTable('crew_members'))->toBeTrue();
    expect(Schema::hasTable('crew_expenses'))->toBeTrue();
});

it('dispatches exactly one webhook for a committed Crew mutation', function (): void {
    $webhook = Webhook::factory()->create(['events' => ['crew.member.created'], 'is_active' => true]);
    $member = CrewMember::factory()->create();
    Queue::fake([DeliverWebhook::class]);

    DB::transaction(function () use ($member): void {
        CrewAudit::record('crew.member.created', $member);
    });

    Queue::assertPushed(DeliverWebhook::class, 1);
    $this->assertDatabaseHas('action_logs', ['action' => 'crew.member.created', 'auditable_id' => $member->id]);
});

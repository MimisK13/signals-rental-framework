<?php

declare(strict_types=1);

use App\Actions\Notifications\LogCommunication;
use App\Domain\Activities\Enums\ActivityType;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityParticipant;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\Lists\Models\ListName;
use App\Events\AuditableEvent;
use App\Jobs\SendNotification;
use App\Models\ActionLog;
use App\Models\Member;
use App\Models\User;
use App\Notifications\DomainEventNotification;
use App\Services\Notifications\NotificationChannelResolver;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

it('preserves the first-run activity seeder command after moving its class', function (): void {
    $this->artisan('db:seed', ['--class' => 'ActivitySeeder', '--force' => true])->assertSuccessful();

    $this->assertDatabaseCount('activities', 0);
});

it('reads historical activity audit rows and retains their morph key for new audit writes', function (): void {
    $activity = Activity::factory()->create();
    $historical = ActionLog::factory()->create([
        'action' => 'activity.updated',
        'auditable_type' => 'App\\Models\\Activity',
        'auditable_id' => $activity->id,
    ]);

    event(new AuditableEvent($activity, 'activity.completed'));

    expect($historical->fresh()->auditable)->toBeInstanceOf(Activity::class);
    expect($activity->getMorphClass())->toBe('App\\Models\\Activity');
    $this->assertDatabaseHas('action_logs', [
        'action' => 'activity.completed',
        'auditable_type' => 'App\\Models\\Activity',
        'auditable_id' => $activity->id,
    ]);
    expect(ActionLog::forEntity($activity->getMorphClass(), $activity->id)->count())->toBe(2);
});

it('resolves historical activity attachments and writes new attachments with the same key', function (): void {
    $activity = Activity::factory()->create();
    $historical = Attachment::factory()->create([
        'attachable_type' => 'App\\Models\\Activity',
        'attachable_id' => $activity->id,
    ]);

    $attachment = Attachment::factory()->for($activity, 'attachable')->create();

    expect($historical->fresh()->attachable)->toBeInstanceOf(Activity::class);
    expect($activity->attachments()->count())->toBe(2);
    $this->assertDatabaseHas('attachments', [
        'id' => $attachment->id,
        'attachable_type' => 'App\\Models\\Activity',
        'attachable_id' => $activity->id,
    ]);
});

it('reads and updates historical custom field values without creating a second entity identity', function (): void {
    $field = CustomField::factory()->string()->create(['name' => 'call_reference', 'module_type' => 'Activity']);
    $activity = Activity::factory()->create();
    $value = CustomFieldValue::query()->create([
        'custom_field_id' => $field->id,
        'entity_type' => 'App\\Models\\Activity',
        'entity_id' => $activity->id,
        'value_string' => 'LEGACY-CALL',
    ]);

    expect($activity->fresh()->custom_fields['call_reference'])->toBe('LEGACY-CALL');
    $activity->syncCustomFields(['call_reference' => 'UPDATED-CALL']);

    expect($value->fresh()->entity)->toBeInstanceOf(Activity::class);
    $this->assertDatabaseHas('custom_field_values', [
        'id' => $value->id,
        'entity_type' => 'App\\Models\\Activity',
        'entity_id' => $activity->id,
        'value_string' => 'UPDATED-CALL',
    ]);
    $this->assertDatabaseCount('custom_field_values', 1);
});

it('delivers an already queued notification whose subject contains the historical activity class', function (): void {
    $activity = Activity::factory()->create();
    $recipient = Member::factory()->create();
    Notification::fake();
    $job = new SendNotification(
        notificationClass: DomainEventNotification::class,
        notificationTypeKey: 'activity.updated',
        recipientId: $recipient->id,
        subjectType: 'App\\Models\\Activity',
        subjectId: $activity->id,
        payload: ['title' => 'Activity updated', 'body' => 'Historical queue subject resolved.'],
        logicalChannels: ['database'],
    );

    $job->handle(app(NotificationChannelResolver::class), app(LogCommunication::class));

    Notification::assertSentTo($recipient, DomainEventNotification::class);
    $this->assertDatabaseHas('communication_log', ['subject_id' => $activity->id, 'notification_type_key' => 'activity.updated', 'status' => 'sent']);
});

it('applies identical policy decisions to historical and canonical activity class names', function (): void {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('activities.view');
    $gate = Gate::forUser($user);

    expect($gate->allows('viewAny', 'App\\Models\\Activity'))->toBeTrue();
    expect($gate->allows('viewAny', Activity::class))->toBeTrue();
    expect($gate->allows('create', 'App\\Models\\Activity'))->toBeFalse();
    expect($gate->allows('create', Activity::class))->toBeFalse();
});

it('resolves both domain factories without replacing the existing Crew factory resolution', function (): void {
    $activity = Activity::factory()->create();
    $participant = ActivityParticipant::factory()->for($activity)->create();
    $crewMember = CrewMember::factory()->create();

    $this->assertModelExists($activity);
    $this->assertModelExists($participant);
    $this->assertModelExists($crewMember);
    expect($participant->activity)->toBeInstanceOf(Activity::class);
    expect($participant->activity_id)->toBe($activity->id);
});

it('keeps historical enum payloads and the original activity type migration compatible', function (): void {
    $legacyCase = 'App\\Enums\\ActivityType:Task';
    $payload = 'E:'.strlen($legacyCase).':"'.$legacyCase.'";';
    $list = ListName::query()->where('name', 'Activity Type')->firstOrFail();

    expect(unserialize($payload))->toBe(ActivityType::Task);
    expect($list->values()->orderBy('sort_order')->pluck('name')->all())->toBe(
        array_map(function (ActivityType $type): string {
            return $type->label();
        }, ActivityType::cases()),
    );
});

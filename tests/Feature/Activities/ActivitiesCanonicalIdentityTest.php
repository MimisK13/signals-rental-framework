<?php

declare(strict_types=1);

use App\Domain\Activities\Database\Seeders\ActivitySeeder;
use App\Domain\Activities\Models\Activity;
use App\Domain\Activities\Models\ActivityParticipant;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\CustomFields\Models\CustomField;
use App\Domain\CustomFields\Models\CustomFieldValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

it('uses only canonical activity class identities and policies', function (): void {
    $activity = Activity::factory()->create();
    $participant = ActivityParticipant::factory()->for($activity)->create();

    expect($activity->getMorphClass())->toBe(Activity::class)
        ->and($participant->getMorphClass())->toBe(ActivityParticipant::class)
        ->and(class_exists('App\\Models\\Activity'))->toBeFalse()
        ->and(class_exists('App\\Models\\ActivityParticipant'))->toBeFalse();

    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo('activities.view');

    expect(Gate::forUser($user)->allows('viewAny', Activity::class))->toBeTrue();
});

it('writes canonical activity identities to polymorphic rows', function (): void {
    $activity = Activity::factory()->create();
    event(new AuditableEvent($activity, 'activity.completed'));
    $attachment = Attachment::factory()->for($activity, 'attachable')->create();
    $field = CustomField::factory()->string()->create(['name' => 'call_reference', 'module_type' => 'Activity']);
    $activity->syncCustomFields(['call_reference' => 'CALL-1']);

    $this->assertDatabaseHas('action_logs', ['auditable_type' => Activity::class, 'auditable_id' => $activity->id]);
    $this->assertDatabaseHas('attachments', ['id' => $attachment->id, 'attachable_type' => Activity::class]);
    $this->assertDatabaseHas('custom_field_values', [
        'custom_field_id' => $field->id,
        'entity_type' => Activity::class,
        'entity_id' => $activity->id,
    ]);
});

it('canonicalizes persisted historical activity identities one table at a time', function (): void {
    $activity = Activity::factory()->create();
    $participant = ActivityParticipant::factory()->for($activity)->create();
    $field = CustomField::factory()->string()->create(['module_type' => 'Activity']);

    DB::table('action_logs')->insert([
        ['action' => 'activity.updated', 'auditable_type' => 'App\\Models\\Activity', 'auditable_id' => $activity->id, 'created_at' => now()],
        ['action' => 'participant.updated', 'auditable_type' => 'App\\Models\\ActivityParticipant', 'auditable_id' => $participant->id, 'created_at' => now()],
    ]);
    DB::table('attachments')->insert([
        'uuid' => fake()->uuid(), 'attachable_type' => 'App\\Models\\Activity', 'attachable_id' => $activity->id,
        'original_name' => 'legacy.txt', 'file_path' => 'legacy.txt', 'disk' => 'local',
        'mime_type' => 'text/plain', 'file_size' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    CustomFieldValue::query()->create([
        'custom_field_id' => $field->id, 'entity_type' => 'App\\Models\\Activity',
        'entity_id' => $activity->id, 'value_string' => 'legacy',
    ]);
    DB::table('communication_log')->insert([
        'notifiable_type' => 'App\\Models\\Activity', 'notifiable_id' => $activity->id,
        'subject_type' => 'App\\Models\\Activity', 'subject_id' => $activity->id,
        'notification_type_key' => 'activity.updated', 'channel' => 'database',
        'recipient_address' => 'legacy', 'status' => 'sent', 'queued_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([
        '2026_11_03_000037_canonicalize_activity_identities_in_action_logs_table.php',
        '2026_11_03_000038_canonicalize_activity_identity_in_attachments_table.php',
        '2026_11_03_000039_canonicalize_activity_identity_in_custom_field_values_table.php',
        '2026_11_03_000040_canonicalize_activity_identity_in_communication_log_table.php',
    ] as $file) {
        $migration = require app_path('Domain/Activities/Database/Migrations/'.$file);
        $migration->up();
    }

    expect(DB::table('action_logs')->where('auditable_type', 'like', 'App\\Models\\Activity%')->count())->toBe(0)
        ->and(DB::table('attachments')->where('attachable_type', Activity::class)->count())->toBe(1)
        ->and(DB::table('custom_field_values')->where('entity_type', Activity::class)->count())->toBe(1)
        ->and(DB::table('communication_log')->where('subject_type', Activity::class)->count())->toBe(1);
});

it('runs the activity seeder through its canonical class', function (): void {
    $this->artisan('db:seed', ['--class' => ActivitySeeder::class, '--force' => true])->assertSuccessful();
});

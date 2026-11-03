<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Communications\Models\CommunicationLog;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\Documents\Models\Document;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use Illuminate\Support\Facades\DB;

function opportunityIdentityMigration(string $sequence): object
{
    $path = glob(app_path("Domain/Projects/Opportunities/Database/Migrations/2026_11_03_{$sequence}_*.php"));

    if (! is_array($path) || count($path) !== 1) {
        throw new RuntimeException("Expected one Opportunity identity migration for sequence {$sequence}.");
    }

    return require $path[0];
}

it('canonicalizes persisted Opportunity model identities without mappings', function (): void {
    $opportunity = Opportunity::factory()->create();
    $activity = Activity::factory()->create();
    $attachment = Attachment::factory()->create();
    $customFieldValue = CustomFieldValue::factory()->create();
    $document = Document::factory()->create();
    $communication = CommunicationLog::factory()->create();
    $actionLog = ActionLog::factory()->create();
    $old = 'App\\Models\\Opportunity';

    DB::table('activities')->where('id', $activity->id)->update(['regarding_type' => $old, 'regarding_id' => $opportunity->id]);
    DB::table('attachments')->where('id', $attachment->id)->update(['attachable_type' => $old, 'attachable_id' => $opportunity->id]);
    DB::table('custom_field_values')->where('id', $customFieldValue->id)->update(['entity_type' => $old, 'entity_id' => $opportunity->id]);
    DB::table('documents')->where('id', $document->id)->update(['documentable_type' => $old, 'documentable_id' => $opportunity->id]);
    DB::table('communication_log')->where('id', $communication->id)->update(['subject_type' => $old, 'subject_id' => $opportunity->id]);
    DB::table('action_logs')->where('id', $actionLog->id)->update(['auditable_type' => $old, 'auditable_id' => $opportunity->id]);

    foreach (['000012', '000013', '000014', '000015', '000016', '000017'] as $sequence) {
        opportunityIdentityMigration($sequence)->up();
    }

    $canonical = Opportunity::class;

    expect(DB::table('activities')->where('id', $activity->id)->value('regarding_type'))->toBe($canonical)
        ->and(DB::table('attachments')->where('id', $attachment->id)->value('attachable_type'))->toBe($canonical)
        ->and(DB::table('custom_field_values')->where('id', $customFieldValue->id)->value('entity_type'))->toBe($canonical)
        ->and(DB::table('documents')->where('id', $document->id)->value('documentable_type'))->toBe($canonical)
        ->and(DB::table('communication_log')->where('id', $communication->id)->value('subject_type'))->toBe($canonical)
        ->and(DB::table('action_logs')->where('id', $actionLog->id)->value('auditable_type'))->toBe($canonical);
});

it('canonicalizes Opportunity Verbs event and state identities', function (): void {
    $eventId = snowflake_id();
    $snapshotId = snowflake_id();
    $stateEventId = snowflake_id();
    $stateId = snowflake_id();

    DB::table('verb_events')->insert([
        'id' => $eventId,
        'type' => 'App\\Verbs\\Events\\Opportunities\\OpportunityCreated',
        'data' => json_encode(['state' => 'App\\Verbs\\States\\OpportunityState'], JSON_THROW_ON_ERROR),
        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('verb_snapshots')->insert([
        'id' => $snapshotId,
        'state_id' => $stateId,
        'type' => 'App\\Verbs\\States\\OpportunityState',
        'data' => json_encode(['event' => 'App\\Verbs\\Events\\Opportunities\\OpportunityCreated'], JSON_THROW_ON_ERROR),
        'last_event_id' => $eventId,
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('verb_state_events')->insert([
        'id' => $stateEventId,
        'event_id' => $eventId,
        'state_id' => $stateId,
        'state_type' => 'App\\Verbs\\States\\OpportunityState',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['000009', '000010', '000011'] as $sequence) {
        opportunityIdentityMigration($sequence)->up();
    }

    expect(DB::table('verb_events')->where('id', $eventId)->value('type'))
        ->toBe('App\\Domain\\Projects\\Opportunities\\Verbs\\Events\\OpportunityCreated')
        ->and(DB::table('verb_snapshots')->where('id', $snapshotId)->value('type'))
        ->toBe('App\\Domain\\Projects\\Opportunities\\Verbs\\States\\OpportunityState')
        ->and(DB::table('verb_state_events')->where('id', $stateEventId)->value('state_type'))
        ->toBe('App\\Domain\\Projects\\Opportunities\\Verbs\\States\\OpportunityState');
});

<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Attachments\Models\Attachment;
use App\Domain\CustomFields\Models\CustomFieldValue;
use App\Domain\Products\Models\Product;
use App\Domain\Products\Models\ProductGroup;
use App\Domain\Quotations\Models\QuotationItem;
use App\Models\ActionLog;
use App\Models\OpportunityItem;
use Illuminate\Support\Facades\DB;

function productIdentityMigration(string $sequence): object
{
    $path = glob(database_path("migrations/2026_11_03_{$sequence}_*.php"));

    if (! is_array($path) || count($path) !== 1) {
        throw new RuntimeException("Expected one Product identity migration for sequence {$sequence}.");
    }

    return require $path[0];
}

it('canonicalizes persisted Product morph identities without aliases', function (): void {
    $product = Product::factory()->create();
    $opportunityItem = OpportunityItem::factory()->create();
    $quotationItem = QuotationItem::factory()->create();
    $activity = Activity::factory()->create();
    $attachment = Attachment::factory()->create();
    $customFieldValue = CustomFieldValue::factory()->create();
    $actionLog = ActionLog::factory()->create();

    DB::table('opportunity_items')->where('id', $opportunityItem->id)->update(['itemable_type' => 'App\\Models\\Product', 'itemable_id' => $product->id]);
    DB::table('quotation_items')->where('id', $quotationItem->id)->update(['itemable_type' => 'App\\Models\\Product', 'itemable_id' => $product->id]);
    DB::table('activities')->where('id', $activity->id)->update(['regarding_type' => 'App\\Models\\Product', 'regarding_id' => $product->id]);
    DB::table('attachments')->where('id', $attachment->id)->update(['attachable_type' => 'App\\Models\\Product', 'attachable_id' => $product->id]);
    DB::table('custom_field_values')->where('id', $customFieldValue->id)->update(['entity_type' => 'App\\Models\\Product', 'entity_id' => $product->id]);
    DB::table('action_logs')->where('id', $actionLog->id)->update(['auditable_type' => 'App\\Models\\Product', 'auditable_id' => $product->id]);

    foreach (['000001', '000002', '000003', '000004', '000005', '000006'] as $sequence) {
        productIdentityMigration($sequence)->up();
    }

    $canonical = Product::class;

    expect(DB::table('opportunity_items')->where('id', $opportunityItem->id)->value('itemable_type'))->toBe($canonical)
        ->and(DB::table('quotation_items')->where('id', $quotationItem->id)->value('itemable_type'))->toBe($canonical)
        ->and(DB::table('activities')->where('id', $activity->id)->value('regarding_type'))->toBe($canonical)
        ->and(DB::table('attachments')->where('id', $attachment->id)->value('attachable_type'))->toBe($canonical)
        ->and(DB::table('custom_field_values')->where('id', $customFieldValue->id)->value('entity_type'))->toBe($canonical)
        ->and(DB::table('action_logs')->where('id', $actionLog->id)->value('auditable_type'))->toBe($canonical);
});

it('canonicalizes Product identities recursively in Verbs payloads', function (): void {
    $eventId = snowflake_id();
    $snapshotId = snowflake_id();
    $stateId = snowflake_id();
    $payload = ['model' => 'App\\Models\\Product', 'nested' => ['type' => 'App\\Models\\ProductGroup']];

    DB::table('verb_events')->insert([
        'id' => $eventId,
        'type' => 'test.product.identity',
        'data' => json_encode($payload, JSON_THROW_ON_ERROR),
        'metadata' => json_encode([], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('verb_snapshots')->insert([
        'id' => $snapshotId,
        'state_id' => $stateId,
        'type' => 'test.product.identity',
        'data' => json_encode($payload, JSON_THROW_ON_ERROR),
        'last_event_id' => $eventId,
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    productIdentityMigration('000007')->up();
    productIdentityMigration('000008')->up();

    $eventData = json_decode((string) DB::table('verb_events')->where('id', $eventId)->value('data'), true, flags: JSON_THROW_ON_ERROR);
    $snapshotData = json_decode((string) DB::table('verb_snapshots')->where('id', $snapshotId)->value('data'), true, flags: JSON_THROW_ON_ERROR);

    expect($eventData)->toBe(['model' => Product::class, 'nested' => ['type' => ProductGroup::class]])
        ->and($snapshotData)->toBe($eventData);
});

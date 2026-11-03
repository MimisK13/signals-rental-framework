<?php

use App\Domain\AuditLog\Models\ActionLog;
use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Enums\AvailabilityEventType;
use App\Domain\Availability\Enums\AvailabilityResolution;
use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\AvailabilityEvent;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Shortages\Enums\ShortagePolicy;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionType;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Services\ShortageEventRecorder;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('logs shortage.detected when the confirmation gate sees a shortage', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);

    $store = Store::factory()->shortagePolicy(ShortagePolicy::Warn)->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 666001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Event test',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));
    (new ConvertToQuotation)($opportunity->fresh());

    (new ConvertToOrder)($opportunity->fresh());

    expect(
        AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::ShortageDetected->value)
            ->where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->exists()
    )->toBeTrue();

    // The acknowledgement is audit-bridged into the action log.
    expect(ActionLog::query()->where('action', 'shortage.acknowledged')->exists())->toBeTrue();
});

it('logs shortage.cleared with a reason', function () {
    $this->actingAs(User::factory()->owner()->create());

    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create();

    $shortage = Shortage::make(
        opportunityItemId: 1,
        opportunityId: 1,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 3,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );

    app(ShortageEventRecorder::class)->cleared($shortage, 'stock_returned');

    $event = AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolved->value)
        ->where('product_id', $product->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->payload['reason'])->toBe('stock_returned');
});

/**
 * Build a persisted resolution for exercising a status-transition recorder
 * method (§9.2). Resolution status transitions are audit-only — they emit a
 * shortage.* domain event for the audit trail / webhook bus, but do NOT write a
 * product/store `availability_events` delta row (those rows model availability
 * deltas keyed on a real product/store, not resolution lifecycle state).
 */
function lifecycleResolution(ShortageResolutionStatus $status): ShortageResolution
{
    return ShortageResolution::factory()->create([
        'resolver_key' => 'transfer',
        'resolution_type' => ShortageResolutionType::Transfer->value,
        'status' => $status->value,
        'quantity_resolved' => 2,
    ]);
}

it('logs shortage.resolution.in_progress as an audit log without an availability event', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::InProgress);

    app(ShortageEventRecorder::class)->resolutionInProgress($resolution);

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionInProgress->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()->where('action', 'shortage.resolution.in_progress')->exists())->toBeTrue();
});

it('logs shortage.resolution.fulfilled as an audit log without an availability event', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::Fulfilled);

    app(ShortageEventRecorder::class)->resolutionFulfilled($resolution);

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionFulfilled->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()->where('action', 'shortage.resolution.fulfilled')->exists())->toBeTrue();
});

it('logs shortage.resolution.failed with the failure reason in the audit log', function () {
    $this->actingAs(User::factory()->owner()->create());

    $resolution = lifecycleResolution(ShortageResolutionStatus::Failed);

    app(ShortageEventRecorder::class)->resolutionFailed($resolution, 'supplier_declined');

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolutionFailed->value)
        ->where('source_id', $resolution->id)
        ->exists())->toBeFalse()
        ->and(ActionLog::query()
            ->where('action', 'shortage.resolution.failed')
            ->exists())->toBeTrue();

    $log = ActionLog::query()->where('action', 'shortage.resolution.failed')->firstOrFail();
    expect(data_get($log->new_values, 'failure_reason'))->toBe('supplier_declined');
});

<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Planning\OpportunityItemPlanningReader;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->store = Store::factory()->create(['name' => 'Main Warehouse', 'timezone' => 'UTC']);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 */
function planningItem(Store $store, Product $product, array $overrides = []): OpportunityItem
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Planning read surface',
        'store_id' => $store->id,
        'starts_at' => '2026-07-10T08:00:00Z',
        'ends_at' => '2026-07-13T18:00:00Z',
        'planning_starts_at' => '2026-07-10T08:00:00Z',
        'planning_ends_at' => '2026-07-13T18:00:00Z',
    ]));

    $opportunity = Opportunity::query()->findOrFail($created->id);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from(array_merge([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => OpportunityItemType::Product->value,
        'quantity' => '10',
        'transaction_type' => LineItemTransactionType::Rental->value,
        'unit_price' => 1000,
    ], $overrides)));

    return OpportunityItem::query()->where('opportunity_id', $opportunity->id)->sole();
}

it('projects legacy and explicit reservation states without persisting read state', function (): void {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 20,
    ]);
    $legacy = planningItem($this->store, $product);
    $explicit = planningItem($this->store, $product);

    (new ChangeItemReservation)($explicit, ChangeItemReservationData::from(['reserved_quantity' => '4']));

    $reader = app(OpportunityItemPlanningReader::class);
    $legacyData = $reader->read($legacy->fresh());
    $explicitData = $reader->read($explicit->fresh());

    expect($legacyData?->reservation_mode)->toBe('legacy')
        ->and($legacyData?->reservation_state)->toBe('legacy')
        ->and($explicitData?->reservation_mode)->toBe('explicit')
        ->and($explicitData?->reservation_state)->toBe('partial')
        ->and($explicitData?->requested_quantity)->toBe('10.00')
        ->and($explicitData?->reserved_quantity)->toBe('4.00')
        ->and($explicitData?->reservation_gap)->toBe('6.00')
        ->and($explicitData?->effective_claim_quantity)->toBe(4)
        ->and($explicitData?->planning_starts_at)->toBe('2026-07-10T08:00:00+00:00')
        ->and($explicitData?->store_name)->toBe('Main Warehouse')
        ->and($explicit->fresh()->reserved_quantity)->toBe('4.00');
});

it('distinguishes explicit zero and full reservation states', function (): void {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 20,
    ]);
    $none = planningItem($this->store, $product);
    $full = planningItem($this->store, $product);

    (new ChangeItemReservation)($none, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    (new ChangeItemReservation)($full, ChangeItemReservationData::from(['reserved_quantity' => '10']));

    $reader = app(OpportunityItemPlanningReader::class);

    expect($reader->read($none->fresh())?->reservation_mode)->toBe('explicit')
        ->and($reader->read($none->fresh())?->reservation_state)->toBe('none')
        ->and($reader->read($none->fresh())?->reserved_quantity)->toBe('0.00')
        ->and($reader->read($full->fresh())?->reservation_state)->toBe('full');
});

it('uses canonical item planning and dispatch store overrides', function (): void {
    $dispatchStore = Store::factory()->create(['name' => 'Dispatch Warehouse', 'timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $dispatchStore->id,
        'quantity_held' => 20,
    ]);
    $item = planningItem($this->store, $product, [
    ]);
    $item->forceFill([
        'planning_starts_at' => '2026-07-11T09:00:00Z',
        'planning_ends_at' => '2026-07-12T17:00:00Z',
        'dispatch_store_id' => $dispatchStore->id,
    ])->saveQuietly();
    $item->refresh();

    $data = app(OpportunityItemPlanningReader::class)->read($item);

    expect($data?->store_id)->toBe($dispatchStore->id)
        ->and($data?->store_name)->toBe('Dispatch Warehouse')
        ->and($data?->planning_starts_at)->toBe('2026-07-11T09:00:00+00:00')
        ->and($data?->planning_ends_at)->toBe('2026-07-12T17:00:00+00:00');
});

it('reports confirmed external supply separately from owned reservation', function (): void {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 6,
    ]);
    $item = planningItem($this->store, $product);
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));

    Demand::factory()->phase(DemandPhase::Committed)->window(
        Carbon::parse('2026-07-10T08:00:00Z'),
        Carbon::parse('2026-07-13T18:00:00Z'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity' => -4,
        'source_type' => 'virtual_stock_intake',
        'source_id' => 987654,
    ]);

    $data = app(OpportunityItemPlanningReader::class)->read($item->fresh());

    expect($data?->reserved_quantity)->toBe('6.00')
        ->and($data?->external_supply_quantity)->toBe(4)
        ->and($data?->available_quantity)->toBe(10)
        ->and($data?->shortage_quantity)->toBe(0);

    Demand::query()
        ->where('source_type', 'virtual_stock_intake')
        ->update(['is_active' => false, 'phase' => DemandPhase::Void->value]);

    $afterRemoval = app(OpportunityItemPlanningReader::class)->read($item->fresh());

    expect($afterRemoval?->external_supply_quantity)->toBe(0)
        ->and($afterRemoval?->shortage_quantity)->toBe(4);
});

it('reports serialized allocation separately from reservation', function (): void {
    $product = Product::factory()->serialised()->create();
    $item = planningItem($this->store, $product, ['quantity' => '4']);
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '4']));
    $assets = StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    foreach ($assets as $asset) {
        $item->assets()->create([
            'id' => fake()->unique()->numberBetween(100000, 999999),
            'state_id' => fake()->unique()->numberBetween(100000, 999999),
            'stock_level_id' => $asset->id,
            'status' => 0,
        ]);
    }

    $data = app(OpportunityItemPlanningReader::class)->read($item->fresh());

    expect($data?->reservation_state)->toBe('full')
        ->and($data?->allocated_quantity)->toBe(2)
        ->and($data?->allocation_state)->toBe('partial')
        ->and($data?->effective_claim_quantity)->toBe(4);
});

it('is read-only', function (): void {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 20,
    ]);
    $item = planningItem($this->store, $product);
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    $before = [
        'reserved_quantity' => $item->fresh()->reserved_quantity,
        'demands' => Demand::query()->where('source_id', $item->id)->count(),
        'assets' => $item->assets()->count(),
    ];

    app(OpportunityItemPlanningReader::class)->read($item->fresh());

    expect([
        'reserved_quantity' => $item->fresh()->reserved_quantity,
        'demands' => Demand::query()->where('source_id', $item->id)->count(),
        'assets' => $item->assets()->count(),
    ])->toBe($before);
});

it('returns null for non-product lines', function (): void {
    $opportunity = Opportunity::factory()->create(['store_id' => $this->store->id]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => OpportunityItemType::Text,
        'itemable_id' => null,
        'itemable_type' => null,
        'quantity' => '1',
    ]);

    expect(app(OpportunityItemPlanningReader::class)->read($item))->toBeNull();
});

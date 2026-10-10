<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\AllocateAsset;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeItemReservation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\AllocateAssetData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\ChangeItemReservationData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\DemandPhase;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Availability\OpportunityItemDemandResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->makeEventDemandItem = function (array $overrides = []): OpportunityItem {
        $store = Store::factory()->create();
        $product = Product::factory()->bulk()->create();
        $opportunity = (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => 'Reservation demand',
            'store_id' => $store->id,
            'starts_at' => '2026-11-01T09:00:00Z',
            'ends_at' => '2026-11-03T17:00:00Z',
        ]));

        (new AddOpportunityItem)(Opportunity::findOrFail($opportunity->id), AddOpportunityItemData::from(array_merge([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'quantity' => '10',
            'transaction_type' => LineItemTransactionType::Rental->value,
            'unit_price' => 1000,
        ], $overrides)));

        return OpportunityItem::query()->where('opportunity_id', $opportunity->id)->sole();
    };
});

it('centralizes legacy and explicit bulk claim quantities in Demand', function (): void {
    $item = ($this->makeEventDemandItem)();
    $resolver = app(OpportunityItemDemandResolver::class);
    $demand = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();

    expect($demand->quantity)->toBe(10)
        ->and($resolver->resolveContext($item)['quantity'])->toBe(10)
        ->and($resolver->resolveContext($item)['claim_quantity'])->toBe(10)
        ->and($demand->phase)->toBe(DemandPhase::Draft)
        ->and($demand->is_active)->toBeFalse();

    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '6']));
    expect(Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole()->quantity)->toBe(6)
        ->and($resolver->resolveContext($item->refresh())['quantity'])->toBe(10)
        ->and($resolver->resolveContext($item->refresh())['claim_quantity'])->toBe(6);

    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '0']));
    expect(Demand::query()->where('source_id', $item->id)->count())->toBe(0);

    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '5']));
    expect(Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole()->quantity)->toBe(5);
});

it('does not increase an explicit claim when requested quantity increases', function (): void {
    $item = ($this->makeEventDemandItem)();
    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '6']));

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '12']));

    expect($item->refresh()->quantity)->toBe('12.00')
        ->and($item->reserved_quantity)->toBe('6.00')
        ->and(Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole()->quantity)->toBe(6);
});

it('resynchronizes the explicit claim after planning and Store changes', function (): void {
    $item = ($this->makeEventDemandItem)();
    (new ChangeItemReservation)($item->refresh(), ChangeItemReservationData::from(['reserved_quantity' => '6']));

    $newStore = Store::factory()->create();
    $opportunity = $item->opportunity()->firstOrFail();
    $opportunity->forceFill([
        'store_id' => $newStore->id,
        'planning_starts_at' => Carbon::parse('2026-10-31T08:00:00Z'),
        'planning_ends_at' => Carbon::parse('2026-11-04T18:00:00Z'),
    ])->save();

    $item->setRelation('opportunity', $opportunity->fresh());
    app(OpportunityItemDemandResolver::class)->syncDemands($item->refresh());

    $demand = Demand::query()->where('source_id', $item->id)->whereNull('asset_id')->sole();

    expect($demand->quantity)->toBe(6)
        ->and($demand->store_id)->toBe($newStore->id)
        ->and($demand->starts_at->toIso8601String())->toBe('2026-10-31T08:00:00+00:00');
});

it('keeps serialized asset claims plus residual capacity within the explicit claim', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create();
    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => '5',
        'reserved_quantity' => '3',
    ]);

    $assets = StockLevel::factory()->serialised()->count(2)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    foreach ($assets as $asset) {
        OpportunityItemAsset::factory()->for($item, 'item')->create(['stock_level_id' => $asset->id]);
    }

    app(OpportunityItemDemandResolver::class)->syncDemands($item);
    $demands = Demand::query()->where('source_id', $item->id)->get();

    expect($demands->whereNotNull('asset_id'))->toHaveCount(2)
        ->and($demands->whereNull('asset_id')->sole()->quantity)->toBe(1);
});

it('rejects serial allocation beyond an explicit reservation', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create();
    $opportunity = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Reservation allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]));

    (new AddOpportunityItem)(Opportunity::findOrFail($opportunity->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '5',
        'transaction_type' => LineItemTransactionType::Rental->value,
        'unit_price' => 1000,
    ]));
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->sole();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '2']));

    $assets = StockLevel::factory()->serialised()->count(3)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assets[0]->id]));
    (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assets[1]->id]));

    expect(fn () => (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $assets[2]->id])))
        ->toThrow(EventNotValid::class);
});

it('rejects serialized allocation when the explicit reservation is zero', function (): void {
    $store = Store::factory()->create();
    $product = Product::factory()->serialised()->create();
    $opportunity = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Zero reservation allocation',
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]));
    (new AddOpportunityItem)(Opportunity::findOrFail($opportunity->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '5',
        'transaction_type' => LineItemTransactionType::Rental->value,
    ]));
    $item = OpportunityItem::query()->where('opportunity_id', $opportunity->id)->sole();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    expect(fn () => (new AllocateAsset)($item->refresh(), AllocateAssetData::from(['stock_level_id' => $asset->id])))
        ->toThrow(EventNotValid::class)
        ->and(OpportunityItemAsset::query()->where('opportunity_item_id', $item->id)->count())->toBe(0)
        ->and(Demand::query()->where('source_id', $item->id)->count())->toBe(0);
});

it('scales catalogue kit component Demand from the explicit claim', function (): void {
    $store = Store::factory()->create();
    $component = Product::factory()->bulk()->create();
    $kit = Product::factory()->kit()->create();
    SerialisedComponent::factory()->pool()->quantity(2)->create([
        'product_id' => $kit->id,
        'component_product_id' => $component->id,
    ]);
    $opportunity = Opportunity::factory()->order()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-11-01T09:00:00Z',
        'ends_at' => '2026-11-03T17:00:00Z',
    ]);
    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_type' => Product::class,
        'itemable_id' => $kit->id,
        'quantity' => '10',
        'reserved_quantity' => '6',
    ]);

    app(OpportunityItemDemandResolver::class)->syncDemands($item);

    expect(Demand::query()->where('source_id', $item->id)->where('product_id', $component->id)->sole()->quantity)->toBe(12);
});

it('rebuilds a positive reservation claim after event replay and resynchronization', function (): void {
    $item = ($this->makeEventDemandItem)();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '6']));
    $opportunityId = $item->opportunity_id;

    Opportunity::query()->whereKey($opportunityId)->withTrashed()->forceDelete();
    Verbs::replay();

    $replayed = OpportunityItem::query()->whereKey($item->id)->firstOrFail();
    app(OpportunityItemDemandResolver::class)->syncDemands($replayed);

    expect($replayed->reserved_quantity)->toBe('6.00')
        ->and(Demand::query()->where('source_id', $replayed->id)->whereNull('asset_id')->sole()->quantity)->toBe(6);
});

it('rebuilds an explicit zero reservation as no owned Demand after replay', function (): void {
    $item = ($this->makeEventDemandItem)();
    (new ChangeItemReservation)($item, ChangeItemReservationData::from(['reserved_quantity' => '0']));
    $opportunityId = $item->opportunity_id;

    Opportunity::query()->whereKey($opportunityId)->withTrashed()->forceDelete();
    Verbs::replay();

    $replayed = OpportunityItem::query()->whereKey($item->id)->firstOrFail();
    app(OpportunityItemDemandResolver::class)->syncDemands($replayed);

    expect($replayed->reserved_quantity)->toBe('0.00')
        ->and(Demand::query()->where('source_id', $replayed->id)->count())->toBe(0);
});

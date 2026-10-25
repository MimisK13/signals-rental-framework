<?php

use App\Actions\Shortages\ApplyBatchShortageResolution;
use App\Actions\Shortages\ApplyShortageResolution;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Shortages\ApplyBatchResolutionData;
use App\Data\Shortages\ApplyResolutionData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Services\Shortages\Resolvers\SubHireResolver;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageBatchGrouper;
use App\Services\Shortages\SubHireSupplierCostQuery;
use App\Services\VirtualStock\VirtualStockNumberGenerator;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
        'shortages.resolvers.subhire.auto_executable' => false,
    ]);

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    $this->supplier = Member::factory()->organisation()->create(['name' => 'Stage Hire Ltd']);

    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 1,
    ]);

    $this->resolver = app(SubHireResolver::class);
});

function subHireShortage(
    Store $store,
    Product $product,
    int $shortfall = 2,
    int $availableAtHome = 0,
): Shortage {
    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => (string) ($availableAtHome + $shortfall),
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: $availableAtHome + $shortfall,
        availableQuantity: $availableAtHome,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

/**
 * @return list<ProductSupplierCost>
 */
function seedSupplierCosts(
    Product $product,
    Member $supplier,
    Store $store,
): array {
    $expensive = ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost' => 5000,
        'cost_basis' => 'per_unit',
        'lead_time_days' => 3,
        'is_preferred' => false,
    ]);

    $preferred = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'supplier_id' => Member::factory()->organisation()->create(['name' => 'Preferred Supplier'])->id,
        'store_id' => null,
        'cost' => 7500,
        'cost_basis' => 'per_unit',
        'lead_time_days' => 1,
        'is_preferred' => true,
    ]);

    return [$preferred, $expensive];
}

it('is not auto-executable by default and identifies as the subhire resolver', function () {
    expect($this->resolver->key())->toBe('subhire')
        ->and($this->resolver->name())->toBe('Sub-hire')
        ->and($this->resolver->priority())->toBe(60)
        ->and($this->resolver->isAutoExecutable())->toBeFalse()
        ->and($this->resolver->supportsBatch())->toBeTrue();
});

it('returns false from canResolve when no supplier cost exists', function () {
    $shortage = subHireShortage($this->store, $this->product);

    expect($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($this->resolver->getOptions($shortage))->toBe([]);
});

it('memoises supplier-cost existence per product and store key', function () {
    $shortage = subHireShortage($this->store, $this->product);
    $queries = 0;

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'from "product_supplier_costs"')) {
            $queries++;
        }
    });

    expect($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($this->resolver->canResolve($shortage))->toBeFalse()
        ->and($queries)->toBe(1);
});

it('does not leak supplier-cost candidates across product and store keys', function () {
    $otherProduct = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    $otherStore = Store::factory()->create(['timezone' => 'UTC']);

    ProductSupplierCost::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'store_id' => $this->store->id,
    ]);

    $query = app(SubHireSupplierCostQuery::class);

    expect($query->hasCandidatesFor($this->product->id, $this->store->id))->toBeTrue()
        ->and($query->hasCandidatesFor($otherProduct->id, $this->store->id))->toBeFalse()
        ->and($query->hasCandidatesFor($this->product->id, $otherStore->id))->toBeFalse();
});

it('orders options preferred first then ascending cost', function () {
    [$preferred] = seedSupplierCosts($this->product, $this->supplier, $this->store);
    $shortage = subHireShortage($this->store, $this->product);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(2)
        ->and($options[0]->metadata['supplier_cost_id'])->toBe($preferred->id)
        ->and($options[0]->metadata['is_preferred'])->toBeTrue()
        ->and($options[0]->quantityResolved)->toBe(2)
        ->and($options[0]->estimatedCost)->toBe(15000)
        ->and($options[0]->estimatedLeadTimeMinutes)->toBe(24 * 60)
        ->and($options[1]->metadata['is_preferred'])->toBeFalse()
        ->and($options[1]->estimatedCost)->toBe(10000);
});

it('sizes per_day costs using the hire window', function () {
    ProductSupplierCost::factory()->create([
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'store_id' => $this->store->id,
        'cost' => 1000,
        'cost_basis' => 'per_day',
        'lead_time_days' => 2,
    ]);

    $shortage = subHireShortage($this->store, $this->product, shortfall: 2);
    $option = $this->resolver->getOptions($shortage)[0];

    // 4 rental days (Jul 1–5) × £10/day × 2 units
    expect($option->estimatedCost)->toBe(8000)
        ->and($option->metadata['cost_basis'])->toBe('per_day');
});

it('apply creates pending resolution draft po and draft intake atomically', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);
    $shortage = subHireShortage($this->store, $this->product);
    $option = $this->resolver->getOptions($shortage)[0];

    $result = $this->resolver->apply($shortage, $option);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->requiresFollowup)->toBeTrue();

    $resolution = $result->resolution->fresh();
    $po = PurchaseOrder::query()->findOrFail((int) $resolution->metadata['purchase_order_id']);
    $intake = VirtualStockIntake::query()->find($resolution->virtual_stock_intake_id);

    expect($resolution->resolver_key)->toBe('subhire')
        ->and($resolution->resolution_type)->toBe(ShortageResolutionType::Subhire)
        ->and($resolution->virtual_stock_intake_id)->not->toBeNull()
        ->and($po)->not->toBeNull()
        ->and($po->po_type)->toBe('sub_hire')
        ->and($po->shortage_resolution_id)->toBe($resolution->id)
        ->and($po->items)->toHaveCount(1)
        ->and($po->items->first()->supplier_cost_id)->toBe($option->metadata['supplier_cost_id'])
        ->and($intake)->not->toBeNull()
        ->and($intake->status)->toBe('draft')
        ->and($intake->purchase_order_item_id)->toBe($po->items->first()->id);

    $this->assertDatabaseHas('shortage_resolution_items', [
        'shortage_resolution_id' => $resolution->id,
        'opportunity_item_id' => $shortage->opportunityItemId,
        'quantity_allocated' => 2,
    ]);
});

it('rolls back po intake and resolution when intake creation fails mid transaction', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);
    $shortage = subHireShortage($this->store, $this->product);
    $option = $this->resolver->getOptions($shortage)[0];

    $this->mock(VirtualStockNumberGenerator::class, function ($mock): void {
        $mock->shouldReceive('nextOrCreate')->andThrow(new RuntimeException('Intake numbering failed'));
    });

    expect(fn () => $this->resolver->apply($shortage, $option))
        ->toThrow(RuntimeException::class, 'Intake numbering failed');

    expect(ShortageResolution::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0)
        ->and(VirtualStockIntake::query()->count())->toBe(0);
});

it('consolidates two opportunities into one po and intake with pivot quantities', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $shortageA = subHireShortage($this->store, $this->product, shortfall: 2);
    $shortageB = subHireShortage($this->store, $this->product, shortfall: 3);

    $group = app(ShortageBatchGrouper::class)->groupsFrom(new ShortageCollection([$shortageA, $shortageB]))[0];
    $option = $this->resolver->getBatchOptions($group)[0];

    $result = $this->resolver->applyBatch($group, $option);

    $resolution = $result->resolution->fresh(['items']);

    expect($resolution->quantity_resolved)->toBe(5)
        ->and(PurchaseOrder::query()->count())->toBe(1)
        ->and(VirtualStockIntake::query()->count())->toBe(1)
        ->and($resolution->items)->toHaveCount(2);

    $allocations = $resolution->items->pluck('quantity_allocated', 'opportunity_item_id')->all();

    expect($allocations[$shortageA->opportunityItemId])->toBe(2)
        ->and($allocations[$shortageB->opportunityItemId])->toBe(3);

    $po = PurchaseOrder::query()->first();
    expect($po->starts_at->utc()->toIso8601String())->toBe($group->dateRangeStart->utc()->toIso8601String())
        ->and($po->items->first()->quantity)->toBe('5.00');
});

it('skips subhire in auto resolver when config is off', function () {
    config(['shortages.resolvers.subhire.auto_executable' => false]);
    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $store = $this->store;
    $store->update([
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['subhire'],
    ]);

    $item = OpportunityItem::query()->find(subHireShortage($store, $this->product)->opportunityItemId);
    $item->opportunity->update(['store_id' => $store->id]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 880001,
        ]);

    expect(app(ShortageAutoResolver::class)->resolve($item->opportunity->fresh()))->toBe(0);
});

it('auto-resolves subhire when config is on', function () {
    config(['shortages.resolvers.subhire.auto_executable' => true]);
    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $store = $this->store;
    $store->update([
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['subhire'],
    ]);

    $shortage = subHireShortage($store, $this->product);
    $item = OpportunityItem::query()->findOrFail($shortage->opportunityItemId);
    $item->opportunity->update(['store_id' => $store->id]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 880002,
        ]);

    expect(app(ShortageAutoResolver::class)->resolve($item->opportunity->fresh()))->toBe(1)
        ->and(ShortageResolution::query()->where('resolver_key', 'subhire')->count())->toBe(1);
});

it('applies via ApplyShortageResolution action', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);
    $shortage = subHireShortage($this->store, $this->product);
    $itemId = $shortage->opportunityItemId;

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 880003,
        ]);

    $result = app(ApplyShortageResolution::class)(ApplyResolutionData::from([
        'opportunity_item_id' => $itemId,
        'resolver_key' => 'subhire',
        'option_index' => 0,
    ]));

    expect($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->resolution->virtual_stock_intake_id)->not->toBeNull();
});

it('applies batch via ApplyBatchShortageResolution action', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $shortageA = subHireShortage($this->store, $this->product, shortfall: 1);
    $shortageB = subHireShortage($this->store, $this->product, shortfall: 2);

    foreach ([880010, 880011] as $index => $sourceId) {
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'quantity' => 5,
                'source_type' => 'opportunity_item',
                'source_id' => $sourceId,
            ]);
    }

    $result = app(ApplyBatchShortageResolution::class)(ApplyBatchResolutionData::from([
        'opportunity_item_ids' => [$shortageA->opportunityItemId, $shortageB->opportunityItemId],
        'resolver_key' => 'subhire',
        'option_index' => 0,
    ]));

    expect($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and(ShortageResolutionItem::query()->where('shortage_resolution_id', $result->resolution->id)->count())->toBe(2);
});

it('applies subhire via the shortages api', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);
    $shortage = subHireShortage($this->store, $this->product);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 880004,
        ]);

    $token = User::factory()->owner()->create()->createToken('test', ['shortages:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shortage_resolutions', [
            'opportunity_item_id' => $shortage->opportunityItemId,
            'resolver_key' => 'subhire',
            'option_index' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('resolution.resolver_key', 'subhire');
});

it('applies batch via the shortages api', function () {
    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $shortageA = subHireShortage($this->store, $this->product, shortfall: 1);
    $shortageB = subHireShortage($this->store, $this->product, shortfall: 2);

    foreach ([880012, 880013] as $sourceId) {
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'quantity' => 5,
                'source_type' => 'opportunity_item',
                'source_id' => $sourceId,
            ]);
    }

    $token = User::factory()->owner()->create()->createToken('test', ['shortages:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/shortage_resolutions/batch', [
            'opportunity_item_ids' => [$shortageA->opportunityItemId, $shortageB->opportunityItemId],
            'resolver_key' => 'subhire',
            'option_index' => 0,
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');
});

it('renders subhire option cards on the shortages panel', function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    seedSupplierCosts($this->product, $this->supplier, $this->store);

    $shortage = subHireShortage($this->store, $this->product);
    $item = OpportunityItem::query()->with('opportunity')->findOrFail($shortage->opportunityItemId);
    $opportunity = $item->opportunity;

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 5,
            'source_type' => 'opportunity_item',
            'source_id' => 880005,
        ]);

    Volt::test('opportunities.shortages', ['opportunity' => $opportunity->fresh()])
        ->assertSee('Sub-hire')
        ->assertSee('Preferred');
});

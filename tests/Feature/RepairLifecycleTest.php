<?php

use App\Actions\Repairs\CompleteRepair;
use App\Actions\Repairs\SetRepairUsability;
use App\Enums\AssetExpenseType;
use App\Enums\DemandPhase;
use App\Enums\RepairStatus;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Member;
use App\Models\Product;
use App\Models\Repair;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\User;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['stock.view', 'stock.adjust']);
    $this->actingAs($user);
});

/**
 * @return array{0: Product, 1: Store, 2: Asset, 3: StockLevel}
 */
function serializedRepairFixture(): array
{
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    $asset = Asset::factory()->create(['product_id' => $product->id]);
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $asset->id,
    ]);

    return [$product, $store, $asset, $stockLevel];
}

it('projects an unusable serialized repair into canonical demand', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();

    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-10 09:00:00',
        'started_at' => '2026-10-10 10:00:00',
        'expected_completed_at' => '2026-10-15 17:00:00',
    ]);

    $demand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($demand->product_id)->toBe($product->id)
        ->and($demand->store_id)->toBe($store->id)
        ->and($demand->asset_id)->not->toBeNull()
        ->and($demand->quantity)->toBe(1)
        ->and($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->is_active)->toBeTrue()
        ->and($demand->starts_at->format('Y-m-d H:i'))->toBe('2026-10-10 10:00')
        ->and($demand->ends_at->toDateString())->toBe('2199-01-01');
});

it('keeps usable repairs out of availability and toggles them explicitly', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_usable' => true,
    ]);

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->exists())->toBeFalse();

    (new SetRepairUsability)($repair, false);
    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole()->is_active)->toBeTrue();

    (new SetRepairUsability)($repair->refresh(), true);
    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole()->is_active)->toBeFalse();
});

it('ends repair demand on completion', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    (new CompleteRepair)($repair);

    $demand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($repair->refresh()->status)->toBe(RepairStatus::Completed)
        ->and($repair->is_usable)->toBeTrue()
        ->and($demand->refresh()->is_active)->toBeFalse()
        ->and($demand->phase)->toBe(DemandPhase::Void);
});

it('projects bulk repair quantity without creating an asset', function (): void {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    $repair = Repair::factory()->forBulk($product, $store, 3)->unusable()->create();
    $demand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($repair->asset_id)->toBeNull()
        ->and($demand->asset_id)->toBeNull()
        ->and($demand->quantity)->toBe(3);
});

it('rebinds serialized repair demand when its stock projection is recreated', function (): void {
    [$product, $store, $asset, $stockLevel] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    $stockLevel->delete();

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->count())->toBe(0);

    $newStockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $asset->id,
    ]);

    $newDemand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($newDemand->refresh()->is_active)->toBeTrue()
        ->and($newDemand->asset_id)->toBe($newStockLevel->id);
});

it('falls back to product quantity when the stock level already has an active asset demand', function (): void {
    [$product, $store, $asset, $stockLevel] = serializedRepairFixture();

    Demand::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $stockLevel->id,
        'quantity' => 1,
        'starts_at' => '2026-10-10 08:00:00',
        'ends_at' => '2026-10-11 08:00:00',
        'buffered_starts_at' => '2026-10-10 08:00:00',
        'buffered_ends_at' => '2026-10-11 08:00:00',
        'source_type' => 'opportunity_item',
        'source_id' => 999,
        'phase' => DemandPhase::Committed,
        'is_active' => true,
    ]);

    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-10 09:00:00',
    ]);

    $demand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($demand->asset_id)->toBeNull()
        ->and($demand->quantity)->toBe(1)
        ->and($demand->metadata['asset_projection'])->toBe('product_quantity');
});

it('links one repair expense and supplier bill item without duplicating the cost', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    $supplier = Member::factory()->organisation()->create();
    $bill = SupplierBill::factory()->create(['supplier_id' => $supplier->id]);
    $billItem = SupplierBillItem::factory()->create([
        'supplier_bill_id' => $bill->id,
        'product_id' => $product->id,
        'repair_id' => $repair->id,
        'total' => 30000,
        'tax' => 0,
    ]);
    $expense = AssetExpense::factory()->create([
        'asset_id' => $asset->id,
        'repair_id' => $repair->id,
        'expense_type' => AssetExpenseType::Repair,
        'base_amount' => 30000,
    ]);

    expect($repair->refresh()->expense->is($expense))->toBeTrue()
        ->and($repair->supplierBillItems->contains($billItem))->toBeTrue()
        ->and($repair->expense()->count())->toBe(1)
        ->and($repair->supplierBillItems()->count())->toBe(1);
});

it('does not return a repaired allocated stock level as an available candidate', function (): void {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    $stockLevels = collect(range(1, 4))->map(function () use ($product, $store): StockLevel {
        $asset = Asset::factory()->create(['product_id' => $product->id]);

        return StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'asset_id' => $asset->id,
        ]);
    });
    $target = $stockLevels->first();
    $asset = $target->asset()->firstOrFail();
    $from = Carbon::parse('2026-10-10 00:00:00');
    $to = Carbon::parse('2026-10-15 00:00:00');

    Demand::factory()->serialised()->phase(DemandPhase::Committed)->window($from, $to)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $target->id,
        'source_type' => 'opportunity_item',
    ]);

    $repair = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-11 00:00:00',
    ]);

    $service = app(AvailabilityService::class);
    $availability = $service->getAvailability($product->id, $store->id, Carbon::parse('2026-10-12 00:00:00'));
    $availableAssets = $service->getAvailableAssets($product->id, $store->id, $from, $to);

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole()->asset_id)->toBeNull()
        ->and($availability->total_stock)->toBe(4)
        ->and($availability->total_demanded)->toBe(2)
        ->and($availability->available)->toBe(2)
        ->and($availableAssets->pluck('id')->all())->toBe($stockLevels->skip(1)->pluck('id')->values()->all());
});

it('keeps serialized repair and allocation conflict visible without claiming a fifth unit', function (): void {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    $stockLevels = collect(range(1, 4))->map(function () use ($product, $store): StockLevel {
        $asset = Asset::factory()->create(['product_id' => $product->id]);

        return StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'asset_id' => $asset->id,
        ]);
    });
    $from = Carbon::parse('2026-10-10 00:00:00');
    $to = Carbon::parse('2026-10-15 00:00:00');

    foreach ($stockLevels as $stockLevel) {
        Demand::factory()->serialised()->phase(DemandPhase::Committed)->window($from, $to)->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'asset_id' => $stockLevel->id,
            'source_type' => 'opportunity_item',
        ]);
    }

    $repair = Repair::factory()->forAsset($stockLevels->first()->asset()->firstOrFail())->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-11 00:00:00',
    ]);

    app(RecalculationPipeline::class)->recalculate($product->id, $store->id, $from, $to);

    $repairDemand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();
    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $product->id)
        ->where('store_id', $store->id)
        ->where('slot_start', '>=', Carbon::parse('2026-10-11 00:00:00'))
        ->where('slot_start', '<', $to)
        ->orderBy('slot_start')
        ->firstOrFail();

    expect(Demand::query()->where('product_id', $product->id)->where('store_id', $store->id)->active()->count())->toBe(5)
        ->and($repairDemand->asset_id)->toBeNull()
        ->and($repairDemand->quantity)->toBe(1)
        ->and($repairDemand->metadata['asset_projection'])->toBe('product_quantity')
        ->and($snapshot->total_stock)->toBe(4)
        ->and($snapshot->total_demanded)->toBe(5)
        ->and($snapshot->available)->toBe(-1)
        ->and(app(AvailabilityService::class)->getAvailableAssets($product->id, $store->id, $from, $to))->toHaveCount(0);
});

it('blocks only the repaired serialized asset before any allocation exists', function (): void {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create();
    $stockLevels = collect(range(1, 4))->map(function () use ($product, $store): StockLevel {
        $asset = Asset::factory()->create(['product_id' => $product->id]);

        return StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'asset_id' => $asset->id,
        ]);
    });
    $target = $stockLevels->first();
    $repair = Repair::factory()->forAsset($target->asset()->firstOrFail())->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-11 00:00:00',
    ]);
    $from = Carbon::parse('2026-10-10 00:00:00');
    $to = Carbon::parse('2026-10-15 00:00:00');
    $service = app(AvailabilityService::class);

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole()->asset_id)->toBe($target->id)
        ->and($service->checkAssetAvailable($target->id, $from, $to))->toBeFalse()
        ->and($service->getAvailableAssets($product->id, $store->id, $from, $to)->pluck('id')->all())->toBe($stockLevels->skip(1)->pluck('id')->values()->all());
});

it('does not release an unusable repair when expected completion has passed', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->inProgress()->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-10 09:00:00',
        'started_at' => '2026-10-10 10:00:00',
        'expected_completed_at' => '2026-10-15 00:00:00',
        'completed_at' => null,
    ]);

    $demand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    expect($repair->status)->toBe(RepairStatus::InProgress)
        ->and($repair->is_usable)->toBeFalse()
        ->and($demand->is_active)->toBeTrue()
        ->and($demand->ends_at->toDateString())->toBe('2199-01-01');
});

it('does not consume capacity twice for overlapping repair reasons on one asset', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $first = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-10 09:00:00',
    ]);
    $second = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-11 09:00:00',
    ]);

    expect(Demand::query()->where('source_type', 'repair')->where('is_active', true)->count())->toBe(1)
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $first->id)->exists())->toBeTrue()
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $second->id)->where('is_active', true)->exists())->toBeFalse();

    (new CompleteRepair)($first->refresh());

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $second->id)->where('is_active', true)->exists())->toBeTrue();

    $third = Repair::factory()->forAsset($asset)->unusable()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'reported_at' => '2026-10-12 09:00:00',
    ]);

    (new SetRepairUsability)($second->refresh(), false);
    (new CompleteRepair)($second->refresh());

    expect(Demand::query()->where('source_type', 'repair')->where('source_id', $first->id)->where('is_active', true)->exists())->toBeFalse()
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $second->id)->where('is_active', true)->exists())->toBeFalse()
        ->and(Demand::query()->where('source_type', 'repair')->where('source_id', $third->id)->where('is_active', true)->exists())->toBeTrue();
});

it('counts linked repair cost once in asset financial expenses', function (): void {
    [$product, $store, $asset] = serializedRepairFixture();
    $repair = Repair::factory()->forAsset($asset)->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
    AssetExpense::factory()->create([
        'asset_id' => $asset->id,
        'repair_id' => $repair->id,
        'expense_type' => AssetExpenseType::Repair,
        'base_currency_code' => 'GBP',
        'base_amount' => 30000,
    ]);

    $result = app(AssetFinancialsCalculator::class)->calculate($asset->refresh());

    expect($result->operatingExpenses)->toBe(30000);
});

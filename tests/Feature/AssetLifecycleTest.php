<?php

use App\Actions\Assets\ConfirmAssetLost;
use App\Actions\Assets\MarkAssetMissing;
use App\Actions\Assets\ReactivateAsset;
use App\Actions\Assets\RestoreAsset;
use App\Actions\Assets\RetireAsset;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetRetirementDisposition;
use App\Enums\DemandPhase;
use App\Enums\RepairStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetUsage;
use App\Models\Demand;
use App\Models\InspectionPolicy;
use App\Models\Product;
use App\Models\Repair;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\Assets\AssetInspectionComplianceReader;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['stock.view', 'stock.adjust']);
    $this->actingAs($user);
});

/** @return array{product: Product, store: Store, asset: Asset, stock: StockLevel} */
function lifecycleFixture(): array
{
    $product = Product::factory()->serialised()->create(['track_availability' => true]);
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'lifecycle_status' => AssetLifecycleStatus::Active,
    ]);
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => $asset->id,
    ]);

    return compact('product', 'store', 'asset', 'stock');
}

it('records explicit lifecycle transitions without deleting operational identity', function (): void {
    $fixture = lifecycleFixture();

    (new MarkAssetMissing)($fixture['asset'], ['reason' => 'Not found after return']);
    (new ConfirmAssetLost)($fixture['asset']->refresh(), ['reason' => 'Confirmed lost']);

    expect($fixture['asset']->refresh()->lifecycleStatus())->toBe(AssetLifecycleStatus::Lost)
        ->and(AssetLifecycleEvent::query()->where('asset_id', $fixture['asset']->id)->count())->toBe(2)
        ->and(StockLevel::query()->whereKey($fixture['stock']->id)->exists())->toBeTrue();
});

it('does not allow generic asset updates to bypass lifecycle actions', function (): void {
    $fixture = lifecycleFixture();

    expect(fn () => $fixture['asset']->update(['lifecycle_status' => AssetLifecycleStatus::Missing]))
        ->toThrow(ValidationException::class);
});

it('excludes missing assets from stock and serialized candidate selection', function (): void {
    $fixture = lifecycleFixture();
    $otherAsset = Asset::factory()->create([
        'product_id' => $fixture['product']->id,
        'lifecycle_status' => AssetLifecycleStatus::Active,
    ]);
    $otherStock = StockLevel::factory()->serialised()->create([
        'product_id' => $fixture['product']->id,
        'store_id' => $fixture['store']->id,
        'asset_id' => $otherAsset->id,
    ]);

    (new MarkAssetMissing)($fixture['asset']);

    $pipeline = app(RecalculationPipeline::class);
    $service = app(AvailabilityService::class);

    expect($pipeline->totalStock($fixture['product'], $fixture['store']->id))->toBe(1)
        ->and($service->getAvailableAssets(
            $fixture['product']->id,
            $fixture['store']->id,
            Carbon::parse('2026-10-10 00:00:00'),
            Carbon::parse('2026-10-11 00:00:00'),
        )->pluck('id')->all())->toBe([$otherStock->id])
        ->and($service->checkAssetAvailable(
            $fixture['stock']->id,
            Carbon::parse('2026-10-10 00:00:00'),
            Carbon::parse('2026-10-11 00:00:00'),
        ))->toBeFalse();
});

it('restores a lost asset without fabricating a rental return', function (): void {
    $fixture = lifecycleFixture();
    $usage = AssetUsage::factory()->create([
        'asset_id' => $fixture['asset']->id,
        'dispatched_at' => '2026-10-10 10:00:00',
        'returned_at' => null,
    ]);

    (new MarkAssetMissing)($fixture['asset']);
    (new ConfirmAssetLost)($fixture['asset']->refresh());
    (new RestoreAsset)($fixture['asset']->refresh(), ['store_id' => $fixture['store']->id, 'location' => 'Repair bench']);

    expect($fixture['asset']->refresh()->lifecycleStatus())->toBe(AssetLifecycleStatus::Active)
        ->and($usage->refresh()->returned_at)->toBeNull()
        ->and($fixture['asset']->currentStockLevel()->firstOrFail()->location)->toBe('Repair bench')
        ->and(AssetLifecycleEvent::query()->where('asset_id', $fixture['asset']->id)->count())->toBe(3);
});

it('recreates a missing stock projection when an asset is found', function (): void {
    $fixture = lifecycleFixture();
    $stockId = $fixture['stock']->id;

    (new MarkAssetMissing)($fixture['asset']);
    $fixture['stock']->delete();

    (new RestoreAsset)($fixture['asset']->refresh(), ['store_id' => $fixture['store']->id]);

    expect($fixture['asset']->refresh()->lifecycleStatus())->toBe(AssetLifecycleStatus::Active)
        ->and($fixture['asset']->currentStockLevel()->exists())->toBeTrue()
        ->and($fixture['asset']->currentStockLevel()->first()->id)->not->toBe($stockId)
        ->and($fixture['asset']->currentStockLevel()->first()->asset_id)->toBe($fixture['asset']->id);
});

it('rejects retirement while an active repair exists', function (): void {
    $fixture = lifecycleFixture();
    Repair::factory()->forAsset($fixture['asset'])->create([
        'product_id' => $fixture['product']->id,
        'store_id' => $fixture['store']->id,
        'status' => RepairStatus::InProgress,
    ]);

    expect(fn () => (new RetireAsset)($fixture['asset']))
        ->toThrow(ValidationException::class);
});

it('does not double-count a repair when the same asset becomes missing', function (): void {
    $fixture = lifecycleFixture();
    $repair = Repair::factory()->forAsset($fixture['asset'])->unusable()->create([
        'product_id' => $fixture['product']->id,
        'store_id' => $fixture['store']->id,
        'reported_at' => '2026-10-10 09:00:00',
    ]);

    $pipeline = app(RecalculationPipeline::class);
    $before = $pipeline->totalStock($fixture['product'], $fixture['store']->id);
    $repairDemand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();

    (new MarkAssetMissing)($fixture['asset']);

    $after = $pipeline->totalStock($fixture['product'], $fixture['store']->id);
    $releasedDemand = $repairDemand->refresh();

    expect($before)->toBe(1)
        ->and($after)->toBe(0)
        ->and($releasedDemand->is_active)->toBeFalse()
        ->and($releasedDemand->phase->value)->toBe('void');
});

it('preserves the same shortage when lifecycle exclusion replaces repair capacity loss', function (): void {
    $fixture = lifecycleFixture();
    Demand::factory()->serialised()->phase(DemandPhase::Committed)->window(
        Carbon::parse('2026-10-10 00:00:00'),
        Carbon::parse('2026-10-11 00:00:00'),
    )->create([
        'product_id' => $fixture['product']->id,
        'store_id' => $fixture['store']->id,
        'asset_id' => $fixture['stock']->id,
        'source_type' => 'opportunity_item',
        'source_id' => 12345,
    ]);
    $repair = Repair::factory()->forAsset($fixture['asset'])->unusable()->create([
        'product_id' => $fixture['product']->id,
        'store_id' => $fixture['store']->id,
        'reported_at' => '2026-10-10 00:00:00',
    ]);
    $service = app(AvailabilityService::class);
    $windowStart = Carbon::parse('2026-10-10 00:00:00');
    $windowEnd = Carbon::parse('2026-10-11 00:00:00');

    $before = $service->getAvailability($fixture['product']->id, $fixture['store']->id, $windowStart);
    (new MarkAssetMissing)($fixture['asset']);
    $after = $service->getAvailability($fixture['product']->id, $fixture['store']->id, $windowStart);

    expect($repair->refresh()->isActive())->toBeTrue()
        ->and($before->available)->toBe(-1)
        ->and($after->available)->toBe(-1)
        ->and($service->checkAssetAvailable($fixture['stock']->id, $windowStart, $windowEnd))->toBeFalse();
});

it('stores terminal disposal proceeds without changing asset financials', function (): void {
    $fixture = lifecycleFixture();
    $fixture['asset']->forceFill([
        'ownership_type' => 'company_owned',
        'base_currency_code' => 'EUR',
        'base_acquisition_amount' => 320000,
    ])->saveQuietly();

    (new RetireAsset)($fixture['asset'], [
        'disposition' => AssetRetirementDisposition::Sold,
        'proceeds_amount' => 150000,
        'proceeds_currency_code' => 'EUR',
    ]);

    $event = AssetLifecycleEvent::query()->where('asset_id', $fixture['asset']->id)->sole();
    $financials = app(AssetFinancialsCalculator::class)->calculate($fixture['asset']->refresh());

    expect($fixture['asset']->lifecycleStatus())->toBe(AssetLifecycleStatus::Retired)
        ->and($event->event_type->value)->toBe('sold')
        ->and($event->proceeds_amount)->toBe(150000)
        ->and($financials->investmentProfit)->toBe(-320000);
});

it('does not allow reactivation after sold disposition', function (): void {
    $fixture = lifecycleFixture();
    $fixture['asset']->forceFill(['ownership_type' => 'company_owned'])->saveQuietly();

    (new RetireAsset)($fixture['asset'], ['disposition' => AssetRetirementDisposition::Sold]);

    expect(fn () => (new ReactivateAsset)($fixture['asset']->refresh()))
        ->toThrow(ValidationException::class);
});

it('excludes retired assets from current product inspection compliance', function (): void {
    $fixture = lifecycleFixture();
    InspectionPolicy::factory()->create([
        'product_id' => $fixture['product']->id,
        'is_active' => true,
    ]);

    (new RetireAsset)($fixture['asset']);
    $summary = app(AssetInspectionComplianceReader::class)->summarizeProduct($fixture['product']->refresh());

    expect($summary['unknown'])->toBe(0)
        ->and($summary['valid'] + $summary['due_soon'] + $summary['overdue'] + $summary['failed'])->toBe(0);
});

<?php

use App\Actions\Stocktakes\CompleteStocktake;
use App\Actions\Stocktakes\CreateStocktake;
use App\Actions\Stocktakes\RecordSerializedStocktakeObservation;
use App\Actions\Stocktakes\StartStocktake;
use App\Data\Stocktakes\CreateStocktakeData;
use App\Enums\AssetLifecycleStatus;
use App\Enums\StocktakeObservationType;
use App\Models\Asset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StocktakeObservation;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->store = Store::factory()->create();
    $this->product = Product::factory()->serialised()->create(['name' => 'M32 Microphone']);
});

function createSerializedStocktakeLevel(Store $store, Product $product, string $assetNumber, string $serialNumber): StockLevel
{
    $asset = Asset::factory()->create(['product_id' => $product->id]);

    return StockLevel::factory()->serialised()->create([
        'store_id' => $store->id,
        'product_id' => $product->id,
        'asset_id' => $asset->id,
        'asset_number' => $assetNumber,
        'serial_number' => $serialNumber,
        'barcode' => 'BC-'.$assetNumber,
        'quantity_held' => 1,
    ]);
}

function startSerializedStocktake(Store $store): Stocktake
{
    return (new StartStocktake)((new CreateStocktake)(CreateStocktakeData::from([
        'store_id' => $store->id,
    ])));
}

it('materializes serialized Asset lines with identity and container snapshots', function (): void {
    $level = createSerializedStocktakeLevel($this->store, $this->product, 'M32-001', 'SN-001');

    $stocktake = startSerializedStocktake($this->store);
    $line = $stocktake->lines()->firstOrFail();

    expect($stocktake->lines)->toHaveCount(1)
        ->and($line->asset_id)->toBe($level->asset_id)
        ->and($line->asset_number_snapshot)->toBe('M32-001')
        ->and($line->serial_number_snapshot)->toBe('SN-001')
        ->and($line->barcode_snapshot)->toBe('BC-M32-001')
        ->and($line->quantity_held_snapshot)->toBe('1.00');
});

it('records expected scans and makes duplicate scans idempotent', function (): void {
    createSerializedStocktakeLevel($this->store, $this->product, 'M32-001', 'SN-001');
    $stocktake = startSerializedStocktake($this->store);

    $first = (new RecordSerializedStocktakeObservation)($stocktake, 'M32-001');
    $duplicate = (new RecordSerializedStocktakeObservation)($stocktake, 'SN-001');

    expect($first->observation_type)->toBe(StocktakeObservationType::SerializedScan)
        ->and($first->stocktake_line_id)->not->toBeNull()
        ->and($duplicate->id)->toBe($first->id)
        ->and(StocktakeObservation::query()->count())->toBe(1)
        ->and(StockTransaction::query()->count())->toBe(0);
});

it('records an unexpected known Asset without mutation', function (): void {
    $otherStore = Store::factory()->create();
    $level = createSerializedStocktakeLevel($otherStore, $this->product, 'M32-004', 'SN-004');
    $stocktake = startSerializedStocktake($this->store);

    $observation = (new RecordSerializedStocktakeObservation)($stocktake, $level->asset_number);

    expect($observation->observation_type)->toBe(StocktakeObservationType::SerializedScan)
        ->and($observation->stocktake_line_id)->toBeNull()
        ->and($observation->asset_id)->toBe($level->asset_id)
        ->and($observation->store_id_snapshot)->toBe($otherStore->id)
        ->and($level->fresh()->store_id)->toBe($otherStore->id)
        ->and(StockTransaction::query()->count())->toBe(0);
});

it('retains unknown identifiers as unresolved evidence', function (): void {
    $stocktake = startSerializedStocktake($this->store);

    $observation = (new RecordSerializedStocktakeObservation)($stocktake, 'UNKNOWN-123', 'Found on shelf B');

    expect($observation->observation_type)->toBe(StocktakeObservationType::UnresolvedItem)
        ->and($observation->identifier)->toBe('UNKNOWN-123')
        ->and($observation->stocktake_line_id)->toBeNull()
        ->and($observation->asset_id)->toBeNull()
        ->and(StockTransaction::query()->count())->toBe(0);
});

it('records Missing and Lost Assets without changing lifecycle', function (): void {
    $missing = createSerializedStocktakeLevel($this->store, $this->product, 'M32-005', 'SN-005');
    $lost = createSerializedStocktakeLevel($this->store, $this->product, 'M32-006', 'SN-006');
    $missing->asset->forceFill(['lifecycle_status' => AssetLifecycleStatus::Missing])->saveQuietly();
    $lost->asset->forceFill(['lifecycle_status' => AssetLifecycleStatus::Lost])->saveQuietly();
    $stocktake = startSerializedStocktake($this->store);

    $missingObservation = (new RecordSerializedStocktakeObservation)($stocktake, 'M32-005');
    $lostObservation = (new RecordSerializedStocktakeObservation)($stocktake, 'M32-006');

    expect($missingObservation->lifecycle_status_snapshot)->toBe(AssetLifecycleStatus::Missing->value)
        ->and($lostObservation->lifecycle_status_snapshot)->toBe(AssetLifecycleStatus::Lost->value)
        ->and($missing->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::Missing)
        ->and($lost->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::Lost)
        ->and(StockTransaction::query()->count())->toBe(0);
});

it('allows completion with serialized Assets not observed', function (): void {
    $level = createSerializedStocktakeLevel($this->store, $this->product, 'M32-009', 'SN-009');
    $stocktake = startSerializedStocktake($this->store);

    $completed = (new CompleteStocktake)($stocktake);

    expect($completed->isCompleted())->toBeTrue()
        ->and($completed->lines()->firstOrFail()->accepted_observation_id)->toBeNull()
        ->and($level->asset->fresh()->lifecycle_status)->toBe(AssetLifecycleStatus::Active)
        ->and(StockTransaction::query()->count())->toBe(0);
});

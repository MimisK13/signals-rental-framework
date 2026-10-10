<?php

use App\Enums\DemandPhase;
use App\Models\Asset;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Product;
use App\Models\Repair;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

it('keeps serialized repair identity and replacement shortage correct on PostgreSQL', function (): void {
    $product = Product::factory()->serialised()->create();
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $stockLevels = collect(range(1, 4))->map(function () use ($product, $store): StockLevel {
        $asset = Asset::factory()->create(['product_id' => $product->id]);

        return StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'asset_id' => $asset->id,
        ]);
    });
    $from = Carbon::parse('2026-10-10 00:00:00Z');
    $repairStartsAt = Carbon::parse('2026-10-11 00:00:00Z');
    $to = Carbon::parse('2026-10-15 00:00:00Z');

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
        'reported_at' => $repairStartsAt,
    ]);

    app(RecalculationPipeline::class)->recalculate($product->id, $store->id, $from, $to);

    $repairDemand = Demand::query()->where('source_type', 'repair')->where('source_id', $repair->id)->sole();
    $snapshot = AvailabilitySnapshot::query()
        ->where('product_id', $product->id)
        ->where('store_id', $store->id)
        ->where('slot_start', '>=', $repairStartsAt)
        ->where('slot_start', '<', $to)
        ->orderBy('slot_start')
        ->firstOrFail();
    $availability = app(AvailabilityService::class);

    expect($repairDemand->asset_id)->toBeNull()
        ->and($repairDemand->metadata['asset_projection'])->toBe('product_quantity')
        ->and($snapshot->total_stock)->toBe(4)
        ->and($snapshot->total_demanded)->toBe(5)
        ->and($snapshot->available)->toBe(-1)
        ->and($availability->getAvailableAssets($product->id, $store->id, $repairStartsAt, $to))->toHaveCount(0);
});

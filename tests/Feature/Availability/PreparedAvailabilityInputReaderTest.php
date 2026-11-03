<?php

use App\Data\Opportunities\OpportunityItemReadContext;
use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\PreparedAvailabilityInputReader;
use App\Domain\Stock\Models\StockLevel;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('bounds stock and demand queries across many unique contexts', function (): void {
    $store = Store::factory()->create();
    $products = Product::factory()->count(25)->bulk()->create();
    $from = Carbon::parse('2026-11-01T08:00:00Z');
    $to = Carbon::parse('2026-11-03T18:00:00Z');
    $contexts = [];

    foreach ($products as $index => $product) {
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity_held' => $index + 1,
        ]);
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window($from->copy()->addHour(), $to->copy()->subHour())
            ->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => 1,
                'source_id' => 1000 + $index,
            ]);
        $contexts[$index + 1] = new OpportunityItemReadContext(
            opportunityItemId: $index + 1,
            productId: $product->id,
            storeId: $store->id,
            from: $from->copy(),
            to: $to->copy(),
            quantity: 1,
            claimQuantity: 1,
        );
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    [$stock, $demands] = app(PreparedAvailabilityInputReader::class)->forContexts($contexts);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $firstProduct = $products->firstOrFail();

    expect($queries)->toHaveCount(2)
        ->and($stock->required($firstProduct->id, $store->id))->toBe(1)
        ->and($stock->required($products->last()->id, $store->id))->toBe(25)
        ->and($demands->required($firstProduct->id, $store->id, $from, $to))->toHaveCount(1)
        ->and(collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], 'stock_levels')))->toHaveCount(1)
        ->and(collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], 'demands')))->toHaveCount(1);
});

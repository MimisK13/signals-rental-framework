<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Planning\OpportunityItemPlanningReader;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('keeps planning reads below the proven per-line query explosion', function (int $lineCount): void {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 1000,
    ]);
    $opportunity = Opportunity::factory()->quotation()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-10 08:00:00',
        'ends_at' => '2026-07-13 18:00:00',
        'planning_starts_at' => '2026-07-10 08:00:00',
        'planning_ends_at' => '2026-07-13 18:00:00',
    ]);

    OpportunityItem::factory()->count($lineCount)->create([
        'opportunity_id' => $opportunity->id,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1.00',
        'path' => '0001',
    ]);

    $queries = 0;
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });

    $planning = app(OpportunityItemPlanningReader::class)->forOpportunity($opportunity->fresh());

    expect($planning)->toHaveCount($lineCount)
        ->and($queries)->toBeLessThan(100);
})->with([
    '20 lines' => 20,
    '50 lines' => 50,
    '100 lines' => 100,
    '250 lines' => 250,
]);

it('batches by effective product store and period instead of line count', function (): void {
    $primaryStore = Store::factory()->create(['timezone' => 'UTC']);
    $secondaryStore = Store::factory()->create(['timezone' => 'UTC']);
    $products = Product::factory()->count(4)->bulk()->create();

    foreach ($products as $product) {
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $primaryStore->id,
            'quantity_held' => 1000,
        ]);
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $secondaryStore->id,
            'quantity_held' => 1000,
        ]);
    }

    $opportunity = Opportunity::factory()->quotation()->create([
        'store_id' => $primaryStore->id,
        'starts_at' => '2026-07-10 08:00:00',
        'ends_at' => '2026-07-13 18:00:00',
        'planning_starts_at' => '2026-07-10 08:00:00',
        'planning_ends_at' => '2026-07-13 18:00:00',
    ]);

    foreach (range(0, 19) as $index) {
        $product = $products[$index % 4];
        $storeId = $index % 2 === 0 ? $primaryStore->id : $secondaryStore->id;

        OpportunityItem::factory()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1.00',
            'path' => str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            'dispatch_store_id' => $storeId === $primaryStore->id ? null : $storeId,
            'planning_starts_at' => $index % 2 === 0 ? null : '2026-07-11 08:00:00',
            'planning_ends_at' => $index % 2 === 0 ? null : '2026-07-12 18:00:00',
        ]);
    }

    $queries = 0;
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });

    $planning = app(OpportunityItemPlanningReader::class)->forOpportunity($opportunity->fresh());

    expect($planning)->toHaveCount(20)
        ->and($queries)->toBeLessThan(80);
});

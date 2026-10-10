<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Planning\OpportunityItemPlanningReader;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

it('keeps a PostgreSQL planning read batched for one hundred lines', function (): void {
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

    OpportunityItem::factory()->count(100)->create([
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

    expect($planning)->toHaveCount(100)
        ->and($queries)->toBeLessThan(100);
});

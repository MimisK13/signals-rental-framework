<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

/**
 * Persist a bulk demand row through the same raw-attribute path production
 * resolvers use (`period` is not fillable — it is set via setRawAttributes).
 */
function pgSaveBulkDemand(Product $product, Store $store, int $quantity, int $sourceId): Demand
{
    $start = Carbon::now();
    $end = Carbon::now()->addDay();

    $demand = new Demand([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'asset_id' => null,
        'quantity' => $quantity,
        'starts_at' => $start,
        'ends_at' => $end,
        'buffered_starts_at' => $start,
        'buffered_ends_at' => $end,
        'source_type' => 'virtual_stock_intake',
        'source_id' => $sourceId,
        'phase' => DemandPhase::Committed->value,
        'is_active' => true,
        'priority' => 0,
        'metadata' => [],
    ]);

    $demand->setRawAttributes(
        array_merge($demand->getAttributes(), [
            'period' => Demand::periodExpression($start, $end),
        ]),
        true,
    );

    $demand->save();

    return $demand;
}

it('allows negative bulk demand rows on postgres', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    $demand = pgSaveBulkDemand($product, $store, -4, 1);

    expect((int) $demand->fresh()->quantity)->toBe(-4);
});

it('rejects zero-quantity bulk demand rows on postgres', function () {
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    expect(fn () => pgSaveBulkDemand($product, $store, 0, 2))
        ->toThrow(QueryException::class);
});

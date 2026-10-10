<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->pipeline = app(RecalculationPipeline::class);
});

it('recalculates without taking the authoritative capacity lock', function (): void {
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 3,
    ]);

    Demand::factory()->phase(DemandPhase::Committed)->window(
        Carbon::parse('2026-09-01T00:00:00Z'),
        Carbon::parse('2026-09-03T00:00:00Z'),
    )->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity' => 1,
    ]);

    $connection = DB::connection('pgsql_testing');
    $connection->enableQueryLog();

    try {
        $result = $this->pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-09-01T00:00:00Z'),
            Carbon::parse('2026-09-03T00:00:00Z'),
        );

        $queries = collect($connection->getQueryLog())->pluck('query');

        expect($result->slots)->toBeGreaterThan(0)
            ->and($queries->contains(fn (string $query): bool => str_contains($query, 'pg_advisory_xact_lock')))->toBeFalse();
    } finally {
        $connection->disableQueryLog();
    }
});

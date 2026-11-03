<?php

use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\DatabaseAvailabilityDataPresence;
use App\Domain\Products\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Schema;

it('returns false when the demands table does not exist (pre-availability-engine schema)', function () {
    // Simulate the M1 state where `demands` has not been migrated yet: the check
    // must short-circuit to false so the resolution setting stays changeable.
    Schema::shouldReceive('hasTable')->with('demands')->andReturn(false);

    expect((new DatabaseAvailabilityDataPresence)->exists())->toBeFalse();
});

it('returns false when the table exists but holds no demands', function () {
    expect(Demand::query()->exists())->toBeFalse()
        ->and((new DatabaseAvailabilityDataPresence)->exists())->toBeFalse();
});

it('returns true once at least one demand row exists', function () {
    $store = Store::factory()->create();
    $product = Product::factory()->bulk()->create();

    Demand::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity' => 1,
    ]);

    expect((new DatabaseAvailabilityDataPresence)->exists())->toBeTrue();
});

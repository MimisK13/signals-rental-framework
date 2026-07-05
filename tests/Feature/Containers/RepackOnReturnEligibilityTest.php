<?php

use App\Enums\ContainerAvailabilityMode;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Containers\RepackOnReturnEligibility;

beforeEach(function () {
    $this->eligibility = app(RepackOnReturnEligibility::class);
    $this->store = Store::factory()->create();
});

it('rejects non-serialised stock levels', function () {
    $housing = StockLevel::factory()->bulk()->create(['store_id' => $this->store->id]);

    expect($this->eligibility->isEligibleHousing($housing))->toBeFalse();
});

it('rejects serialised assets whose product is not containerable', function () {
    $product = Product::factory()->serialised()->create(['is_containerable' => false]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    expect($this->eligibility->isEligibleHousing($housing))->toBeFalse();
});

it('rejects transport housings when repack-on-return is disabled', function () {
    $product = Product::factory()->serialised()->create([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Transport,
        'container_repack_on_return' => false,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    expect($this->eligibility->isEligibleHousing($housing))->toBeFalse();
});

it('rejects transport housings that already have an active container record', function () {
    $product = Product::factory()->serialised()->create([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Transport,
        'container_repack_on_return' => true,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'serialised_item_id' => $housing->id,
    ]);

    expect($this->eligibility->isEligibleHousing($housing))->toBeFalse();
});

it('accepts eligible transport housings without an active container', function () {
    $product = Product::factory()->serialised()->create([
        'is_containerable' => true,
        'container_availability_mode' => ContainerAvailabilityMode::Transport,
        'container_repack_on_return' => true,
    ]);
    $housing = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    expect($this->eligibility->isEligibleHousing($housing))->toBeTrue();
});

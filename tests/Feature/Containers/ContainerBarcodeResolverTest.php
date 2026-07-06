<?php

use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Containers\ContainerBarcodeResolver;

beforeEach(function () {
    $this->resolver = app(ContainerBarcodeResolver::class);
    $this->store = Store::factory()->create();
});

it('returns null for blank barcodes', function () {
    expect($this->resolver->resolve('   '))->toBeNull();
});

it('resolves an active container by barcode', function () {
    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'barcode' => 'C-1001',
    ]);

    $result = $this->resolver->resolve('C-1001');

    expect($result)->toMatchArray(['type' => 'container'])
        ->and($result['container']->is($container))->toBeTrue();
});

it('resolves a housing asset and linked container when present', function () {
    $product = Product::factory()->serialised()->create(['is_containerable' => true]);
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'asset_number' => 'ASSET-900',
    ]);
    $container = Container::factory()->kit()->create([
        'store_id' => $this->store->id,
        'serialised_item_id' => $stockLevel->id,
    ]);

    $result = $this->resolver->resolve('ASSET-900');

    expect($result)->toMatchArray(['type' => 'housing'])
        ->and($result['stock_level']->is($stockLevel))->toBeTrue()
        ->and($result['container']->is($container))->toBeTrue();
});

it('returns housing without a container when no active housing container exists', function () {
    $product = Product::factory()->serialised()->create();
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'serial_number' => 'SN-42',
    ]);

    $result = $this->resolver->resolve('SN-42');

    expect($result)->toMatchArray(['type' => 'housing', 'container' => null])
        ->and($result['stock_level']->is($stockLevel))->toBeTrue();
});

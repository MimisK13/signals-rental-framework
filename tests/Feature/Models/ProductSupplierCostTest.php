<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use Illuminate\Database\QueryException;

it('creates a supplier cost via factory', function () {
    $cost = ProductSupplierCost::factory()->create();

    expect($cost->product_id)->toBeInt()
        ->and($cost->supplier_id)->toBeInt()
        ->and($cost->cost)->toBeInt()
        ->and($cost->currency_code)->toBe('GBP')
        ->and($cost->cost_basis)->toBe('per_unit')
        ->and($cost->is_preferred)->toBeFalse();
});

it('formats cost as a decimal string', function () {
    $cost = ProductSupplierCost::factory()->create(['cost' => 12550, 'currency_code' => 'GBP']);

    expect($cost->formatMoneyCost('cost'))->toBe('125.50');
});

it('scopes forProduct and preferred', function () {
    $product = Product::factory()->create();
    ProductSupplierCost::factory()->count(2)->create(['product_id' => $product->id]);
    ProductSupplierCost::factory()->preferred()->create(['product_id' => $product->id]);
    ProductSupplierCost::factory()->create();

    expect(ProductSupplierCost::query()->forProduct($product->id)->count())->toBe(3)
        ->and(ProductSupplierCost::query()->forProduct($product->id)->preferred()->count())->toBe(1);
});

it('factory preferred state sets is_preferred', function () {
    $cost = ProductSupplierCost::factory()->preferred()->create();

    expect($cost->is_preferred)->toBeTrue();
});

it('factory forStore state assigns a store', function () {
    $store = Store::factory()->create();
    $cost = ProductSupplierCost::factory()->forStore($store)->create();

    expect($cost->store_id)->toBe($store->id);
});

it('relates to product, supplier organisation, and store', function () {
    $supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    $store = Store::factory()->create();
    $cost = ProductSupplierCost::factory()->create([
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
    ]);

    expect($cost->product)->toBeInstanceOf(Product::class)
        ->and($cost->supplier->id)->toBe($supplier->id)
        ->and($cost->store->id)->toBe($store->id);
});

it('enforces unique product supplier store cost_basis at database level for non-null store', function () {
    $product = Product::factory()->create();
    $supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    $store = Store::factory()->create();

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]);

    expect(fn () => ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]))->toThrow(QueryException::class);
});

it('casts min_order_quantity as a decimal string', function () {
    $cost = ProductSupplierCost::factory()->create(['min_order_quantity' => '2.50']);

    expect($cost->min_order_quantity)->toBe('2.50');
});

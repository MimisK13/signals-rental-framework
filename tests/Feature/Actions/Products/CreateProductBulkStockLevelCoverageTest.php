<?php

use App\Domain\Products\Actions\CreateProduct;
use App\Domain\Products\Data\CreateProductData;
use App\Domain\Products\Enums\ProductType;
use App\Domain\Stock\Enums\StockCategory;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('auto-creates a zero-quantity stock level for a bulk product at the default store', function () {
    // A bulk product needs a single stock level ready to receive transactions. With
    // a default store present, CreateProduct provisions it up front at zero qty.
    $store = Store::factory()->create(['is_default' => true]);

    $result = (new CreateProduct)(CreateProductData::from([
        'name' => 'Bulk Cable Reel',
        'product_type' => ProductType::Rental->value,
        'stock_method' => StockMethod::Bulk->value,
    ]));

    $stockLevel = StockLevel::query()
        ->where('product_id', $result->id)
        ->where('store_id', $store->id)
        ->first();

    expect($stockLevel)->not->toBeNull()
        ->and($stockLevel->stock_category)->toBe(StockCategory::BulkStock)
        ->and((float) $stockLevel->quantity_held)->toBe(0.0)
        ->and((float) $stockLevel->quantity_allocated)->toBe(0.0)
        ->and((float) $stockLevel->quantity_unavailable)->toBe(0.0)
        ->and((float) $stockLevel->quantity_on_order)->toBe(0.0);
});

it('falls back to the first store when none is marked default', function () {
    // No default store, but a store exists — the auto stock level lands on it.
    $store = Store::factory()->create(['is_default' => false]);

    $result = (new CreateProduct)(CreateProductData::from([
        'name' => 'Bulk Sandbag',
        'product_type' => ProductType::Rental->value,
        'stock_method' => StockMethod::Bulk->value,
    ]));

    expect(StockLevel::query()->where('product_id', $result->id)->where('store_id', $store->id)->exists())->toBeTrue();
});

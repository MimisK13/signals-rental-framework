<?php

use App\Domain\Products\Models\Product;
use App\Domain\Stock\Actions\CreateStockLevel;
use App\Domain\Stock\Data\CreateStockLevelData;
use App\Domain\Stock\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('rejects a second stock level for a bulk product', function () {
    // Bulk products track stock in exactly one stock level, so creating a second is
    // rejected as a field-scoped validation error.
    $product = Product::factory()->bulk()->create();
    $store = Store::factory()->create();

    StockLevel::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);

    $second = StockLevel::factory()->make([
        'product_id' => $product->id,
        'store_id' => Store::factory()->create()->id,
    ]);

    expect(fn () => (new CreateStockLevel)(CreateStockLevelData::from([
        'product_id' => $product->id,
        'store_id' => $second->store_id,
        'quantity_held' => 3,
    ])))->toThrow(ValidationException::class, 'Bulk products can only have a single stock level.');

    // Still exactly one stock level for the bulk product.
    expect(StockLevel::query()->where('product_id', $product->id)->count())->toBe(1);
});

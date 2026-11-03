<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Actions\CreateStockTransaction;
use App\Domain\Stock\Data\CreateStockTransactionData;
use App\Domain\Stock\Enums\TransactionType;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stock\Models\StockTransaction;
use App\Domain\Stores\Models\Store;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('rejects a fractional transaction quantity', function () {
    // Transactions move whole units only — a fractional quantity is rejected and no
    // transaction row is written.
    $stockLevel = StockLevel::factory()->create([
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    expect(fn () => (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $this->store->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '2.5',
    ])))->toThrow(ValidationException::class, 'Transaction quantity must be a whole number.');

    expect(StockTransaction::query()->where('stock_level_id', $stockLevel->id)->count())->toBe(0);
});

it('rejects a serialised stock movement of more than one unit', function () {
    // Serialised stock moves exactly one unit per transaction; a quantity of two is
    // rejected before anything is written.
    $product = Product::factory()->serialised()->create();
    $stockLevel = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    expect(fn () => (new CreateStockTransaction)(CreateStockTransactionData::from([
        'stock_level_id' => $stockLevel->id,
        'store_id' => $this->store->id,
        'transaction_type' => TransactionType::Buy->value,
        'quantity' => '2.0',
    ])))->toThrow(ValidationException::class, 'Serialised stock can only move one unit per transaction.');

    expect(StockTransaction::query()->where('stock_level_id', $stockLevel->id)->count())->toBe(0);
});

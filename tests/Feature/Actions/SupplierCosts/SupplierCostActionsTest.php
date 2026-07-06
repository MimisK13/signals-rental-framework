<?php

use App\Actions\SupplierCosts\CreateSupplierCost;
use App\Actions\SupplierCosts\DeleteSupplierCost;
use App\Actions\SupplierCosts\SetPreferredSupplier;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Data\SupplierCosts\CreateSupplierCostData;
use App\Data\SupplierCosts\UpdateSupplierCostData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 */
function supplierCostData(array $overrides = []): CreateSupplierCostData
{
    return CreateSupplierCostData::from(array_merge([
        'product_id' => Product::factory()->create()->id,
        'supplier_id' => Member::factory()->create(['membership_type' => MembershipType::Organisation])->id,
        'cost_basis' => 'per_unit',
        'cost' => 5000,
        'currency_code' => 'GBP',
        'min_order_quantity' => '1.00',
    ], $overrides));
}

it('creates a supplier cost', function () {
    $result = (new CreateSupplierCost)(supplierCostData());

    expect($result->cost)->toBe('50.00')
        ->and($result->cost_basis)->toBe('per_unit');

    $this->assertDatabaseHas('product_supplier_costs', ['id' => $result->id, 'cost' => 5000]);
});

it('forbids creating without permission', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => (new CreateSupplierCost)(supplierCostData()))
        ->toThrow(AuthorizationException::class);
});

it('rejects duplicate supplier store basis combinations', function () {
    $product = Product::factory()->create();
    $supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    $store = Store::factory()->create();

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_day',
    ]);

    expect(fn () => (new CreateSupplierCost)(supplierCostData([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_day',
    ])))->toThrow(ValidationException::class);
});

it('rejects duplicate null-store scope combinations via validation', function () {
    $product = Product::factory()->create();
    $supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);

    ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => null,
        'cost_basis' => 'flat',
    ]);

    expect(fn () => (new CreateSupplierCost)(supplierCostData([
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'store_id' => null,
        'cost_basis' => 'flat',
    ])))->toThrow(ValidationException::class);
});

it('updates a supplier cost', function () {
    $cost = ProductSupplierCost::factory()->create(['cost' => 1000]);

    $result = (new UpdateSupplierCost)($cost, UpdateSupplierCostData::from([
        'cost' => 8000,
        'min_order_quantity' => '2.00',
    ]));

    expect($result->cost)->toBe('80.00')
        ->and($result->min_order_quantity)->toBe('2.00');
});

it('deletes a supplier cost', function () {
    $cost = ProductSupplierCost::factory()->create();

    (new DeleteSupplierCost)($cost);

    $this->assertDatabaseMissing('product_supplier_costs', ['id' => $cost->id]);
});

it('sets preferred and unsets siblings in the same product store basis scope', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $existingPreferred = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]);
    $target = ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]);
    $otherBasis = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_day',
    ]);
    $otherProductPreferred = ProductSupplierCost::factory()->preferred()->create([
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]);

    $result = (new SetPreferredSupplier)($target);

    expect($result->is_preferred)->toBeTrue()
        ->and($existingPreferred->fresh()->is_preferred)->toBeFalse()
        ->and($otherBasis->fresh()->is_preferred)->toBeTrue()
        ->and($otherProductPreferred->fresh()->is_preferred)->toBeTrue();
});

it('clears siblings when creating with is_preferred true', function () {
    $product = Product::factory()->create();
    $existingPreferred = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => null,
        'cost_basis' => 'flat',
    ]);

    $result = (new CreateSupplierCost)(supplierCostData([
        'product_id' => $product->id,
        'store_id' => null,
        'cost_basis' => 'flat',
        'is_preferred' => true,
    ]));

    expect($result->is_preferred)->toBeTrue()
        ->and($existingPreferred->fresh()->is_preferred)->toBeFalse();
});

it('isolates preferred siblings by null vs specific store scope', function () {
    $product = Product::factory()->create();
    $store = Store::factory()->create();

    $allStoresPreferred = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $product->id,
        'store_id' => null,
        'cost_basis' => 'per_unit',
    ]);
    $storeScoped = ProductSupplierCost::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'cost_basis' => 'per_unit',
    ]);

    (new SetPreferredSupplier)($storeScoped);

    expect($allStoresPreferred->fresh()->is_preferred)->toBeTrue()
        ->and($storeScoped->fresh()->is_preferred)->toBeTrue();
});

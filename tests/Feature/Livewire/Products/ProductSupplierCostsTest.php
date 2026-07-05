<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
});

it('renders the supplier costs tab for permitted users', function () {
    $this->actingAs($this->owner)
        ->get(route('products.supplier-costs', $this->product))
        ->assertOk()
        ->assertSee('Supplier Costs');
});

it('lists supplier costs on the tab', function () {
    $cost = ProductSupplierCost::factory()->create([
        'product_id' => $this->product->id,
        'cost' => 5000,
    ]);

    $this->actingAs($this->owner);

    Volt::test('products.supplier-costs', ['product' => $this->product])
        ->assertSee($cost->supplier->name)
        ->assertSee('50.00');
});

it('forbids users without costs.view permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['products.view']);

    $this->actingAs($user)
        ->get(route('products.supplier-costs', $this->product))
        ->assertForbidden();
});

it('hides the supplier costs tab without costs.view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['products.view']);

    $this->actingAs($user)
        ->get(route('products.show', $this->product))
        ->assertOk()
        ->assertDontSee('Supplier Costs');
});

it('creates a supplier cost via the modal', function () {
    $this->actingAs($this->owner);
    $supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);

    Volt::test('products.supplier-costs', ['product' => $this->product])
        ->call('openCreateModal')
        ->set('supplierId', $supplier->id)
        ->set('costBasis', 'per_day')
        ->set('cost', '12.50')
        ->set('currencyCode', 'GBP')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showFormModal', false);

    $this->assertDatabaseHas('product_supplier_costs', [
        'product_id' => $this->product->id,
        'supplier_id' => $supplier->id,
        'cost_basis' => 'per_day',
        'cost' => 1250,
    ]);
});

it('sets preferred from the tab', function () {
    $this->actingAs($this->owner);

    $existing = ProductSupplierCost::factory()->preferred()->create([
        'product_id' => $this->product->id,
        'store_id' => null,
        'cost_basis' => 'flat',
    ]);
    $target = ProductSupplierCost::factory()->create([
        'product_id' => $this->product->id,
        'store_id' => null,
        'cost_basis' => 'flat',
    ]);

    Volt::test('products.supplier-costs', ['product' => $this->product])
        ->call('setPreferred', $target->id);

    expect($existing->fresh()->is_preferred)->toBeFalse()
        ->and($target->fresh()->is_preferred)->toBeTrue();
});

it('deletes a supplier cost from the tab', function () {
    $this->actingAs($this->owner);
    $cost = ProductSupplierCost::factory()->create(['product_id' => $this->product->id]);

    Volt::test('products.supplier-costs', ['product' => $this->product])
        ->call('deleteCost', $cost->id);

    $this->assertDatabaseMissing('product_supplier_costs', ['id' => $cost->id]);
});

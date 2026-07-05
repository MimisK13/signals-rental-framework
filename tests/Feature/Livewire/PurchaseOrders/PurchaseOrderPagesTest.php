<?php

use App\Models\Member;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('renders the purchase orders index for authorised users', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['costs.view', 'purchase_orders.access', 'purchase_orders.view']);

    $this->actingAs($user)
        ->get(route('purchase-orders.index'))
        ->assertOk()
        ->assertSee('Purchase Orders');
});

it('lists purchase orders on the index page', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['costs.view', 'purchase_orders.access', 'purchase_orders.view']);
    $this->actingAs($user);

    $po = PurchaseOrder::factory()->create(['number' => 'PO-LIST-1']);

    Volt::test('purchase-orders.index')
        ->assertSee('PO-LIST-1');
});

it('shows send and cancel actions on the show page', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo([
        'costs.view',
        'purchase_orders.access',
        'purchase_orders.view',
        'purchase_orders.send',
        'purchase_orders.cancel',
    ]);
    $this->actingAs($user);

    $po = PurchaseOrder::factory()->create(['status' => 'draft', 'number' => 'PO-SHOW-1']);

    Volt::test('purchase-orders.show', ['purchaseOrder' => $po])
        ->assertSee('PO-SHOW-1')
        ->assertSee('Send')
        ->assertSee('Cancel');
});

it('creates a purchase order from the form', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo([
        'costs.view',
        'purchase_orders.access',
        'purchase_orders.view',
        'purchase_orders.create',
    ]);
    $this->actingAs($user);

    $store = Store::factory()->create();
    $supplier = Member::factory()->organisation()->create();

    Volt::test('purchase-orders.form')
        ->set('supplierId', $supplier->id)
        ->set('storeId', $store->id)
        ->set('lines.0.description', 'Test item')
        ->set('lines.0.quantity', '1.00')
        ->set('lines.0.unit_cost', '10.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(PurchaseOrder::query()->where('supplier_id', $supplier->id)->exists())->toBeTrue();
});

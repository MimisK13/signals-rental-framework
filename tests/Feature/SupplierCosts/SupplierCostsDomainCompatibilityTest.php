<?php

declare(strict_types=1);

use App\Domain\Activities\Models\Activity;
use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Products\Models\Product;
use App\Domain\PurchaseOrders\Models\PurchaseOrderItem;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use Illuminate\Support\Facades\Gate;

it('discovers the policy for the canonical model', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $owner = User::factory()->owner()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['costs.view', 'products.view']);
    $cost = ProductSupplierCost::factory()->create();

    expect(Gate::forUser($owner)->allows('update', $cost))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('view', $cost))->toBeTrue();
    expect(Gate::forUser($viewer)->allows('update', $cost))->toBeFalse();
    expect(Gate::forUser($owner)->allows('viewAny', ProductSupplierCost::class))->toBeTrue();
});

it('keeps product and purchase order relationships on the canonical model', function (): void {
    $product = Product::factory()->create();
    $cost = ProductSupplierCost::factory()->for($product)->create();
    $item = PurchaseOrderItem::factory()->create(['supplier_cost_id' => $cost->id]);

    expect($product->supplierCosts()->whereKey($cost->id)->first())->toBeInstanceOf(ProductSupplierCost::class);
    expect($item->supplierCost)->toBeInstanceOf(ProductSupplierCost::class);
    $this->assertModelExists(Activity::factory()->create());
    $this->assertModelExists(CrewMember::factory()->create());
});

it('serves the native supplier costs route', function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $user = User::factory()->owner()->create();
    $product = Product::factory()->create(['name' => 'Supplier cost product']);
    ProductSupplierCost::factory()->for($product)->create();

    $this->actingAs($user)->withoutVite()->get(route('products.supplier-costs', $product))
        ->assertOk()
        ->assertSee('Supplier Costs');
});

arch('SupplierCosts presentation does not depend on Volt', function (): void {
    expect('App\Domain\SupplierCosts')->not->toUse('Livewire\Volt');
});

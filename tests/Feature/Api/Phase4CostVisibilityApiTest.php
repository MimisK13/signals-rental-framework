<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

/**
 * @param  list<string>  $abilities
 */
function phase4CostVisibilityToken(User $user, array $abilities): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * @param  list<string>  $permissions
 */
function phase4CostVisibilityUser(array $permissions, ?int $storeId = null): User
{
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false, 'is_active' => true]);
    $user->givePermissionTo($permissions);

    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $storeId ?? Store::factory()->create()->id,
        'created_at' => now(),
    ]);

    return $user;
}

describe('Phase 4 cost visibility', function () {
    it('denies purchase order API access without costs.view', function () {
        $user = phase4CostVisibilityUser(['purchase_orders.access', 'purchase_orders.view']);
        $token = phase4CostVisibilityToken($user, ['purchase_orders:read']);

        PurchaseOrder::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/purchase_orders')
            ->assertForbidden();
    });

    it('denies virtual stock API access without costs.view', function () {
        $store = Store::factory()->create();
        $user = phase4CostVisibilityUser(['virtual_stock.access', 'virtual_stock.view'], $store->id);
        Sanctum::actingAs($user, ['virtual_stock:read']);

        VirtualStockIntake::factory()->create(['store_id' => $store->id]);

        $this->getJson('/api/v1/virtual_stock_intakes')
            ->assertForbidden();
    });

    it('denies supplier cost API access without costs.view', function () {
        $restricted = User::factory()->create(['is_owner' => false, 'is_active' => true]);
        $restricted->givePermissionTo('products.view');
        Sanctum::actingAs($restricted, ['products:read']);
        $this->actingAs($restricted);

        $product = Product::factory()->create();
        ProductSupplierCost::factory()->create(['product_id' => $product->id]);

        $this->getJson("/api/v1/products/{$product->id}/supplier_costs")
            ->assertForbidden();
    });
});

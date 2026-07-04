<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Support\Formatter;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Volt\Volt;

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

    it('strips purchase_price from product API responses without costs.view', function () {
        $user = phase4CostVisibilityUser(['products.access', 'products.view']);
        $token = phase4CostVisibilityToken($user, ['products:read']);

        $product = Product::factory()->create(['purchase_price' => 123400]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk();

        expect($response->json('product'))->not->toHaveKey('purchase_price');
    });

    it('includes purchase_price in product API responses with costs.view', function () {
        $user = phase4CostVisibilityUser(['products.access', 'products.view', 'costs.view']);
        $token = phase4CostVisibilityToken($user, ['products:read']);

        $product = Product::factory()->create(['purchase_price' => 123400]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.purchase_price', '1234.00');
    });

    it('includes cost rows but strips amount on opportunity include=costs without costs.view', function () {
        $store = Store::factory()->create();
        $user = phase4CostVisibilityUser(['opportunities.access', 'opportunities.view'], $store->id);
        $token = phase4CostVisibilityToken($user, ['opportunities:read']);

        $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);
        $cost = OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'description' => 'Crew overtime',
            'amount' => 5000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=costs")
            ->assertOk()
            ->assertJsonPath('opportunity.costs.0.id', $cost->id)
            ->assertJsonPath('opportunity.costs.0.description', 'Crew overtime')
            ->assertJsonPath('opportunity.costs.0.cost_type_label', $cost->cost_type->label());

        expect($response->json('opportunity.costs.0'))->not->toHaveKey('amount');
    });

    it('includes cost amount on opportunity include=costs with costs.view', function () {
        $store = Store::factory()->create();
        $user = phase4CostVisibilityUser(['opportunities.access', 'opportunities.view', 'costs.view'], $store->id);
        $token = phase4CostVisibilityToken($user, ['opportunities:read']);

        $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);
        $cost = OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'amount' => 5000,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/opportunities/{$opportunity->id}?include=costs")
            ->assertOk()
            ->assertJsonPath('opportunity.costs.0.id', $cost->id)
            ->assertJsonPath('opportunity.costs.0.amount', '50.00');
    });

    it('hides purchase price on the product show page without costs.view', function () {
        $user = phase4CostVisibilityUser(['products.access', 'products.view']);
        $this->actingAs($user);

        $product = Product::factory()->create(['purchase_price' => 123400]);
        $formatted = app(Formatter::class)->money(123400);

        Volt::test('products.show', ['product' => $product])
            ->assertDontSee('Purchase Price')
            ->assertDontSee($formatted);
    });

    it('hides cost amounts on the opportunity costs page without costs.view', function () {
        $store = Store::factory()->create();
        $user = phase4CostVisibilityUser(['opportunities.access', 'opportunities.view'], $store->id);
        $this->actingAs($user);

        $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);
        OpportunityCost::factory()->create([
            'opportunity_id' => $opportunity->id,
            'description' => 'Unique Van Hire Cost',
            'amount' => 5000,
        ]);

        Volt::test('opportunities.costs', ['opportunity' => $opportunity])
            ->assertSee('Unique Van Hire Cost')
            ->assertDontSee('Amount')
            ->assertDontSee(app(Formatter::class)->money(5000));
    });
});

<?php

use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->supplier = Member::factory()->create(['membership_type' => MembershipType::Organisation]);
});

describe('GET /api/v1/products/{product}/supplier_costs', function () {
    it('lists supplier costs with pagination meta', function () {
        ProductSupplierCost::factory()->count(2)->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
        ]);
        ProductSupplierCost::factory()->create();

        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs")
            ->assertOk()
            ->assertJsonStructure([
                'supplier_costs' => [
                    '*' => ['id', 'product_id', 'supplier_id', 'cost', 'currency_code', 'cost_basis', 'is_preferred', 'min_order_quantity'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json('supplier_costs'))->toHaveCount(2);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/products/{$this->product->id}/supplier_costs")->assertUnauthorized();
    });

    it('requires products:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs")
            ->assertForbidden();
    });

    it('returns 403 without costs.view permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.view']);
        $token = $user->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs")
            ->assertForbidden();
    });

    it('allows access with costs.view and products.view', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.view', 'costs.view']);
        $token = $user->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs")
            ->assertOk();
    });

    it('filters by is_preferred with the _true predicate', function () {
        ProductSupplierCost::factory()->preferred()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
        ]);
        ProductSupplierCost::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => Member::factory()->create(['membership_type' => MembershipType::Organisation])->id,
        ]);

        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs?q[is_preferred_true]=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes supplier and store relationships', function () {
        $store = Store::factory()->create();
        ProductSupplierCost::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $store->id,
        ]);

        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/supplier_costs?include=supplier,store")
            ->assertOk();

        expect($response->json('supplier_costs.0.supplier.name'))->toBe($this->supplier->name)
            ->and($response->json('supplier_costs.0.store.name'))->toBe($store->name);
    });
});

describe('POST /api/v1/products/{product}/supplier_costs', function () {
    it('creates a supplier cost', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/supplier_costs", [
                'supplier_id' => $this->supplier->id,
                'cost_basis' => 'per_unit',
                'cost' => '45.00',
                'currency_code' => 'GBP',
                'min_order_quantity' => '1.00',
            ])
            ->assertCreated()
            ->assertJsonPath('supplier_cost.cost', '45.00');

        $this->assertDatabaseHas('product_supplier_costs', [
            'id' => $response->json('supplier_cost.id'),
            'product_id' => $this->product->id,
            'cost' => 4500,
        ]);
    });

    it('returns validation errors for invalid payload', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/supplier_costs", [
                'supplier_id' => $this->supplier->id,
                'cost_basis' => 'invalid_basis',
                'cost' => '45.00',
                'currency_code' => 'GBP',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_basis']);
    });

    it('returns 403 without costs.view on write', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.view', 'products.supplier_costs']);
        $token = $user->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/supplier_costs", [
                'supplier_id' => $this->supplier->id,
                'cost_basis' => 'per_unit',
                'cost' => '45.00',
                'currency_code' => 'GBP',
            ])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/products/{product}/supplier_costs/{supplierCost}', function () {
    it('updates a supplier cost belonging to the product', function () {
        $cost = ProductSupplierCost::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'cost' => 1000,
        ]);

        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/supplier_costs/{$cost->id}", [
                'cost' => '99.00',
            ])
            ->assertOk()
            ->assertJsonPath('supplier_cost.cost', '99.00');
    });

    it('returns 404 when the cost belongs to another product', function () {
        $otherProduct = Product::factory()->create();
        $cost = ProductSupplierCost::factory()->create([
            'product_id' => $otherProduct->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/supplier_costs/{$cost->id}", [
                'cost' => '99.00',
            ])
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/products/{product}/supplier_costs/{supplierCost}', function () {
    it('deletes a supplier cost', function () {
        $cost = ProductSupplierCost::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/supplier_costs/{$cost->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_supplier_costs', ['id' => $cost->id]);
    });

    it('returns 404 for cross-product delete', function () {
        $otherProduct = Product::factory()->create();
        $cost = ProductSupplierCost::factory()->create([
            'product_id' => $otherProduct->id,
            'supplier_id' => $this->supplier->id,
        ]);

        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/supplier_costs/{$cost->id}")
            ->assertNotFound();
    });
});

describe('POST /api/v1/products/{product}/supplier_costs/{supplierCost}/set_preferred', function () {
    it('marks a supplier cost as preferred', function () {
        $existing = ProductSupplierCost::factory()->preferred()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => null,
            'cost_basis' => 'per_unit',
        ]);
        $target = ProductSupplierCost::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => Member::factory()->create(['membership_type' => MembershipType::Organisation])->id,
            'store_id' => null,
            'cost_basis' => 'per_unit',
        ]);

        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/supplier_costs/{$target->id}/set_preferred")
            ->assertOk()
            ->assertJsonPath('supplier_cost.is_preferred', true);

        expect($existing->fresh()->is_preferred)->toBeFalse();
    });
});

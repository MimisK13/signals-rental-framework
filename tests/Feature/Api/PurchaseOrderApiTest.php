<?php

use App\Models\Member;
use App\Models\MemberStore;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

/**
 * @param  list<string>  $abilities
 */
function purchaseOrderApiToken(User $user, array $abilities = ['purchase_orders:read', 'purchase_orders:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

/**
 * @param  list<string>  $permissions
 */
function purchaseOrderApiUser(array $permissions, ?int $storeId = null): User
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

describe('GET /api/v1/purchase_orders', function () {
    it('lists purchase orders with pagination meta', function () {
        PurchaseOrder::factory()->count(2)->create();
        $token = purchaseOrderApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/purchase_orders')
            ->assertOk()
            ->assertJsonStructure([
                'purchase_orders' => [
                    '*' => ['id', 'number', 'po_type', 'status', 'total'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 2);
    });

    it('filters by status using Ransack', function () {
        PurchaseOrder::factory()->create(['status' => 'draft']);
        PurchaseOrder::factory()->sent()->create();
        $token = purchaseOrderApiToken($this->owner);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/purchase_orders?q[status_eq]=draft')
            ->assertOk();

        expect($response->json('purchase_orders'))->toHaveCount(1)
            ->and($response->json('purchase_orders.0.status'))->toBe('draft');
    });

    it('strips cost fields without costs.view', function () {
        $store = Store::factory()->create();
        $user = purchaseOrderApiUser(['purchase_orders.access', 'purchase_orders.view'], $store->id);
        $token = purchaseOrderApiToken($user);

        $po = PurchaseOrder::factory()->create([
            'store_id' => $store->id,
            'total' => 12000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/purchase_orders')
            ->assertOk()
            ->assertJsonPath('purchase_orders.0.id', $po->id);

        expect($response->json('purchase_orders.0'))->not->toHaveKey('total');
    });
});

describe('GET /api/v1/purchase_orders/{purchase_order}', function () {
    it('returns 404 for cross-store access', function () {
        $po = PurchaseOrder::factory()->create();
        $user = purchaseOrderApiUser(['costs.view', 'purchase_orders.access', 'purchase_orders.view']);
        $token = purchaseOrderApiToken($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/purchase_orders/{$po->id}")
            ->assertNotFound();
    });
});

describe('POST /api/v1/purchase_orders', function () {
    it('creates a draft purchase order', function () {
        $store = Store::factory()->create();
        $supplier = Member::factory()->organisation()->create();
        $token = purchaseOrderApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/purchase_orders', [
                'supplier_id' => $supplier->id,
                'store_id' => $store->id,
                'po_type' => 'purchase',
                'notes' => 'Manual draft',
            ])
            ->assertCreated()
            ->assertJsonPath('purchase_order.status', 'draft')
            ->assertJsonPath('purchase_order.notes', 'Manual draft');
    });
});

describe('purchase order lifecycle endpoints', function () {
    it('sends and cancels a draft purchase order', function () {
        $token = purchaseOrderApiToken($this->owner);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/purchase_orders', [
                'supplier_id' => Member::factory()->organisation()->create()->id,
                'store_id' => Store::factory()->create()->id,
                'po_type' => 'purchase',
            ])
            ->assertCreated()
            ->json('purchase_order.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/purchase_orders/{$created}/send")
            ->assertOk()
            ->assertJsonPath('purchase_order.status', 'sent');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/purchase_orders/{$created}/cancel", ['reason' => 'Changed mind'])
            ->assertOk()
            ->assertJsonPath('purchase_order.status', 'cancelled');
    });

    it('soft-deletes a draft purchase order', function () {
        $this->actingAs($this->owner);
        $po = createTestPurchaseOrder();
        $token = purchaseOrderApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/purchase_orders/{$po->id}")
            ->assertNoContent();

        expect(PurchaseOrder::query()->find($po->id))->toBeNull();
    });
});

describe('PATCH /api/v1/purchase_orders/{purchase_order}', function () {
    it('updates a draft purchase order', function () {
        $this->actingAs($this->owner);
        $po = createTestPurchaseOrder();
        $token = purchaseOrderApiToken($this->owner);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/purchase_orders/{$po->id}", [
                'supplier_id' => $po->supplier_id,
                'store_id' => $po->store_id,
                'po_type' => 'purchase',
                'lines' => [[
                    'description' => 'Updated via API',
                    'quantity' => '2.00',
                    'unit_cost' => 1200,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('purchase_order.items.0.description', 'Updated via API');
    });
});

<?php

use App\Models\Member;
use App\Models\MemberStore;
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
function virtualStockApiToken(User $user, array $abilities = ['virtual_stock:read', 'virtual_stock:write']): string
{
    return $user->createToken('test', $abilities)->plainTextToken;
}

it('lists virtual stock intakes', function () {
    $this->actingAs($this->owner);
    createTestVirtualStockIntake();
    $token = virtualStockApiToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/virtual_stock_intakes')
        ->assertOk()
        ->assertJsonStructure([
            'virtual_stock_intakes' => [
                '*' => ['id', 'number', 'status', 'quantity'],
            ],
            'meta' => ['total', 'per_page', 'page'],
        ]);
});

it('returns 403 without costs.view', function () {
    $store = Store::factory()->create();
    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id, 'is_owner' => false]);
    $user->givePermissionTo(['virtual_stock.access', 'virtual_stock.view']);
    MemberStore::create(['member_id' => $member->id, 'store_id' => $store->id, 'created_at' => now()]);
    $token = virtualStockApiToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/virtual_stock_intakes')
        ->assertForbidden();
});

it('confirms a draft intake via API', function () {
    $this->actingAs($this->owner);
    $intake = createTestVirtualStockIntake();
    $token = virtualStockApiToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/virtual_stock_intakes/{$intake->id}/confirm")
        ->assertOk()
        ->assertJsonPath('virtual_stock_intake.status', 'confirmed');
});

it('returns 404 for missing intakes', function () {
    $token = virtualStockApiToken($this->owner);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/virtual_stock_intakes/999999')
        ->assertNotFound();
});

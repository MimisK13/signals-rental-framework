<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->serialised()->create();
    $this->component = Product::factory()->serialised()->create();
});

describe('GET /api/v1/products/{product}/container_template', function () {
    it('returns container configuration with products:read', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/container_template")
            ->assertOk()
            ->assertJsonPath('container_template.is_containerable', false);
    });

    it('requires products:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/container_template")
            ->assertForbidden();
    });
});

describe('PUT /api/v1/products/{product}/container_template', function () {
    it('updates a valid template', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/container_template", [
                'is_containerable' => true,
                'container_availability_mode' => 'kit',
                'container_max_nesting_depth' => 2,
                'container_repack_on_return' => false,
                'is_warehouse_addable' => false,
                'container_template' => [
                    'slots' => [
                        ['product_id' => $this->component->id, 'quantity' => 2, 'binding' => 'fixed'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('container_template.is_containerable', true)
            ->assertJsonPath('container_template.container_template.slots.0.product_id', $this->component->id);
    });

    it('returns per-row validation errors for self-reference', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/container_template", [
                'is_containerable' => true,
                'container_availability_mode' => 'kit',
                'container_max_nesting_depth' => 2,
                'container_repack_on_return' => false,
                'is_warehouse_addable' => false,
                'container_template' => [
                    'slots' => [
                        ['product_id' => $this->product->id, 'quantity' => 1, 'binding' => 'fixed'],
                    ],
                ],
            ])
            ->assertUnprocessable();

        expect($response->json('errors'))->toHaveKey('container_template.slots.0.product_id');
    });

    it('returns per-row binding validation errors', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/container_template", [
                'is_containerable' => true,
                'container_availability_mode' => 'kit',
                'container_max_nesting_depth' => 2,
                'container_repack_on_return' => false,
                'is_warehouse_addable' => false,
                'container_template' => [
                    'slots' => [
                        ['product_id' => $this->component->id, 'quantity' => 1, 'binding' => 'pool'],
                    ],
                ],
            ])
            ->assertUnprocessable();

        expect($response->json('errors'))->toHaveKey('container_template.slots.0.binding');
    });

    it('returns per-row quantity validation errors', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/container_template", [
                'is_containerable' => true,
                'container_availability_mode' => 'kit',
                'container_max_nesting_depth' => 2,
                'container_repack_on_return' => false,
                'is_warehouse_addable' => false,
                'container_template' => [
                    'slots' => [
                        ['product_id' => $this->component->id, 'quantity' => 0, 'binding' => 'fixed'],
                    ],
                ],
            ])
            ->assertUnprocessable();

        expect($response->json('errors'))->toHaveKey('container_template.slots.0.quantity');
    });

    it('forbids users without manage_templates permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['products.view', 'products.edit']);
        $token = $user->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/container_template", [
                'is_containerable' => false,
                'container_max_nesting_depth' => 2,
                'container_repack_on_return' => false,
                'is_warehouse_addable' => false,
            ])
            ->assertForbidden();
    });
});

<?php

use App\Models\User;
use App\Services\Api\ApiEndpointCatalogue;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/endpoints', function () {
    it('lists catalogue entries with weights and abilities', function () {
        $this->mock(ApiEndpointCatalogue::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([
                [
                    'route_name' => 'api.v1.products.index',
                    'method' => 'GET',
                    'uri' => '/api/v1/products',
                    'resource' => 'products',
                    'operation' => 'index',
                    'required_ability' => 'products:read',
                    'price_weight' => 1,
                ],
                [
                    'route_name' => 'api.v1.usage.index',
                    'method' => 'GET',
                    'uri' => '/api/v1/usage',
                    'resource' => 'usage',
                    'operation' => 'index',
                    'required_ability' => 'api-usage:read',
                    'price_weight' => 0,
                ],
            ]);
        });

        $token = $this->owner->createToken('test', ['system:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/endpoints')
            ->assertOk()
            ->assertJsonStructure([
                'endpoints' => [
                    '*' => ['name', 'method', 'uri', 'resource', 'operation', 'ability', 'price_weight'],
                ],
            ]);

        expect($response->json('endpoints.0'))->toMatchArray([
            'name' => 'api.v1.products.index',
            'method' => 'GET',
            'uri' => '/api/v1/products',
            'resource' => 'products',
            'operation' => 'index',
            'ability' => 'products:read',
            'price_weight' => 1,
        ]);

        expect($response->json('endpoints.1.price_weight'))->toBe(0);
    });

    it('requires system:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/endpoints')
            ->assertForbidden();
    });

    it('requires api.access permission for non-owner users', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['system:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/endpoints')
            ->assertForbidden();
    });

    it('allows users with api.access permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access');

        $this->mock(ApiEndpointCatalogue::class, function ($mock): void {
            $mock->shouldReceive('all')->once()->andReturn([]);
        });

        $token = $user->createToken('test', ['system:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/endpoints')
            ->assertOk()
            ->assertJsonPath('endpoints', []);
    });
});

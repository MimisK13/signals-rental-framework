<?php

use App\Enums\MeteringChannel;
use App\Models\RequestLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/request_logs', function () {
    it('lists request logs with pagination meta', function () {
        RequestLog::factory()->count(3)->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['api-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs')
            ->assertOk()
            ->assertJsonStructure([
                'request_logs' => [
                    '*' => [
                        'id', 'channel', 'request_id', 'method', 'uri', 'route_name',
                        'resource', 'operation', 'personal_access_token_id', 'user_id',
                        'ability', 'status_code', 'duration_ms', 'request_bytes',
                        'response_bytes', 'price_weight', 'weighted_units', 'ip_address',
                        'user_agent', 'created_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonCount(3, 'request_logs');
    });

    it('filters by route_name using ransack', function () {
        RequestLog::factory()->forUser($this->owner)->create(['route_name' => 'api.v1.products.index']);
        RequestLog::factory()->forUser($this->owner)->create(['route_name' => 'api.v1.members.index']);
        $token = $this->owner->createToken('test', ['api-log:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs?q[route_name_eq]=api.v1.products.index')
            ->assertOk();

        expect($response->json('request_logs'))->toHaveCount(1)
            ->and($response->json('request_logs.0.route_name'))->toBe('api.v1.products.index');
    });

    it('filters by status_code and channel', function () {
        RequestLog::factory()->forUser($this->owner)->create([
            'status_code' => 200,
            'channel' => MeteringChannel::Api->value,
        ]);
        RequestLog::factory()->forUser($this->owner)->failed(500)->create([
            'channel' => MeteringChannel::WebhookSent->value,
        ]);
        $token = $this->owner->createToken('test', ['api-log:read'])->plainTextToken;

        $statusResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs?q[status_code_eq]=500')
            ->assertOk();

        expect($statusResponse->json('request_logs'))->toHaveCount(1)
            ->and($statusResponse->json('request_logs.0.status_code'))->toBe(500);

        $channelResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs?q[channel_eq]=webhook_sent')
            ->assertOk();

        expect($channelResponse->json('request_logs'))->toHaveCount(1)
            ->and($channelResponse->json('request_logs.0.channel'))->toBe('webhook_sent');
    });

    it('paginates results', function () {
        RequestLog::factory()->count(5)->forUser($this->owner)->create();
        $token = $this->owner->createToken('test', ['api-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs?per_page=2&page=1')
            ->assertOk()
            ->assertJsonCount(2, 'request_logs')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.page', 1);
    });

    it('requires api-log:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs')
            ->assertForbidden();
    });

    it('requires api.logs.view permission for non-owner users', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs')
            ->assertForbidden();
    });

    it('allows users with api.logs.view permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access', 'api.logs.view');
        $token = $user->createToken('test', ['api-log:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/request_logs')
            ->assertOk();
    });
});

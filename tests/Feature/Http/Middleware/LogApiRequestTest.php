<?php

use App\Enums\MeteringChannel;
use App\Models\Product;
use App\Models\RequestLog;
use App\Models\UsageDaily;
use App\Models\User;
use App\Services\Api\ApiEndpointCatalogue;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
        'api_metering.logging_enabled' => true,
        'api_metering.buffered' => false,
        'api_metering.capture_bodies' => false,
    ]);

    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('writes request logs and usage rollups for authenticated api requests', function () {
    Product::factory()->count(2)->create();
    $tokenResult = $this->owner->createToken('metering-test', ['products:read']);

    $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
        ->getJson('/api/v1/products')
        ->assertOk();

    $token = PersonalAccessToken::findToken($tokenResult->plainTextToken);

    $log = RequestLog::query()->sole();

    expect($log->route_name)->toBe('api.v1.products.index')
        ->and($log->personal_access_token_id)->toBe($token->id)
        ->and($log->user_id)->toBe($this->owner->id)
        ->and($log->status_code)->toBe(200)
        ->and($log->price_weight)->toBe(1)
        ->and($log->weighted_units)->toBe(1)
        ->and($log->request_body)->toBeNull()
        ->and($log->response_body)->toBeNull();

    $rollup = UsageDaily::query()->sole();

    expect($rollup->channel)->toBe(MeteringChannel::Api->value)
        ->and($rollup->key)->toBe('api.v1.products.index')
        ->and($rollup->personal_access_token_id)->toBe($token->id)
        ->and($rollup->status_class)->toBe('2xx')
        ->and($rollup->request_count)->toBe(1)
        ->and($rollup->weighted_units)->toBe(1);
});

it('logs zero weighted units for weight zero schema endpoints', function () {
    $tokenResult = $this->owner->createToken('schema-test', ['schema:read']);

    $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
        ->getJson('/api/v1/schema')
        ->assertOk();

    $log = RequestLog::query()->sole();

    expect($log->route_name)->toBe('api.v1.schema.index')
        ->and($log->price_weight)->toBe(0)
        ->and($log->weighted_units)->toBe(0);

    expect(UsageDaily::query()->sole()->weighted_units)->toBe(0);
});

it('captures redacted and truncated bodies when body capture is enabled', function () {
    config([
        'api_metering.capture_bodies' => true,
        'api_metering.body_max_bytes' => 64,
    ]);

    $this->mock(ApiEndpointCatalogue::class, function ($mock): void {
        $mock->shouldReceive('get')->andReturn([
            'resource' => 'products',
            'operation' => 'store',
            'required_ability' => 'products:write',
        ]);
    });

    $tokenResult = $this->owner->createToken('body-test', ['products:write']);

    $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
        ->postJson('/api/v1/products', [
            'name' => 'Metered Product',
            'password' => 'secret-value',
            'product_type' => 'not-a-valid-type',
            'description' => str_repeat('A', 120),
        ])
        ->assertUnprocessable();

    $log = RequestLog::query()->sole();

    expect($log->request_body)->not->toBeNull()
        ->and($log->request_body)->toContain('[REDACTED]')
        ->and($log->request_body)->not->toContain('secret-value')
        ->and(strlen($log->request_body))->toBeLessThanOrEqual(64)
        ->and($log->response_body)->not->toBeNull();
});

it('reuses an incoming request id header when present', function () {
    $tokenResult = $this->owner->createToken('request-id-test', ['products:read']);
    $requestId = '11111111-2222-4333-8444-555555555555';

    $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
        ->withHeader('X-Request-Id', $requestId)
        ->getJson('/api/v1/products')
        ->assertOk();

    expect(RequestLog::query()->value('request_id'))->toBe($requestId);
});

<?php

use App\Enums\MeteringChannel;
use App\Models\RequestLog;
use App\Models\UsageDaily;
use App\Models\User;
use App\Services\Api\UsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config([
        'api_metering.buffered' => false,
        'api_metering.buffer_key' => 'api-metering:buffer',
    ]);
});

it('direct writes request logs and usage rollups with null token identifiers', function () {
    $recorder = app(UsageRecorder::class);

    $recorder->record([
        'channel' => MeteringChannel::Api->value,
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/products',
        'route_name' => 'api.v1.products.index',
        'resource' => 'products',
        'operation' => 'index',
        'rollup_key' => 'api.v1.products.index',
        'personal_access_token_id' => null,
        'user_id' => null,
        'ability' => null,
        'status_code' => 401,
        'duration_ms' => 12,
        'request_bytes' => 0,
        'response_bytes' => 32,
        'price_weight' => 1,
        'weighted_units' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ]);

    $log = RequestLog::query()->sole();

    expect($log->personal_access_token_id)->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->status_code)->toBe(401);

    $rollup = UsageDaily::query()->sole();

    expect($rollup->personal_access_token_id)->toBeNull()
        ->and($rollup->status_class)->toBe('4xx')
        ->and($rollup->request_count)->toBe(1)
        ->and($rollup->weighted_units)->toBe(1);
});

it('flushes buffered entries into request logs and usage daily rollups', function () {
    config(['api_metering.buffered' => true]);

    $entry = [
        'channel' => MeteringChannel::Api->value,
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/schema',
        'route_name' => 'api.v1.schema.index',
        'resource' => 'schema',
        'operation' => 'index',
        'rollup_key' => 'api.v1.schema.index',
        'personal_access_token_id' => null,
        'user_id' => null,
        'ability' => 'schema:read',
        'status_code' => 200,
        'duration_ms' => 5,
        'request_bytes' => 0,
        'response_bytes' => 128,
        'price_weight' => 0,
        'weighted_units' => 0,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ];

    $connection = Mockery::mock();
    $connection->shouldReceive('ping')->andReturn(true);
    $connection->shouldReceive('lpush')
        ->once()
        ->with('api-metering:buffer', json_encode($entry, JSON_THROW_ON_ERROR))
        ->andReturn(1);
    $connection->shouldReceive('rpop')
        ->once()
        ->with('api-metering:buffer')
        ->andReturn(json_encode($entry, JSON_THROW_ON_ERROR));
    $connection->shouldReceive('rpop')
        ->once()
        ->with('api-metering:buffer')
        ->andReturn(null);

    Redis::shouldReceive('connection')->andReturn($connection);

    $recorder = app(UsageRecorder::class);
    $recorder->record($entry);

    expect(RequestLog::query()->count())->toBe(0)
        ->and(UsageDaily::query()->count())->toBe(0);

    $flushed = $recorder->flush();

    expect($flushed)->toBe(1)
        ->and(RequestLog::query()->count())->toBe(1)
        ->and(UsageDaily::query()->sole()->key)->toBe('api.v1.schema.index')
        ->and(UsageDaily::query()->sole()->weighted_units)->toBe(0);
});

it('still records a request whose token was deleted before the write', function () {
    // The API Explorer mints an ephemeral token per request and revokes it the
    // moment the round-trip completes, so the deferred metering write references
    // a token that no longer exists. The insert must survive and log the request.
    $user = User::factory()->create();
    $token = $user->createToken('api-explorer')->accessToken;
    $tokenId = $token->id;
    $token->delete();

    $recorder = app(UsageRecorder::class);

    $recorder->record([
        'channel' => MeteringChannel::Api->value,
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/products',
        'route_name' => 'api.v1.products.index',
        'resource' => 'products',
        'operation' => 'index',
        'rollup_key' => 'api.v1.products.index',
        'personal_access_token_id' => $tokenId,
        'user_id' => $user->id,
        'ability' => 'products:read',
        'status_code' => 200,
        'duration_ms' => 7,
        'request_bytes' => 0,
        'response_bytes' => 64,
        'price_weight' => 1,
        'weighted_units' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ]);

    $log = RequestLog::query()->sole();

    expect($log->personal_access_token_id)->toBeNull()
        ->and($log->route_name)->toBe('api.v1.products.index')
        ->and($log->user_id)->toBe($user->id);

    expect(UsageDaily::query()->sole()->personal_access_token_id)->toBeNull();
});

it('preserves the token reference when the token still exists', function () {
    $user = User::factory()->create();
    $tokenId = $user->createToken('live-token')->accessToken->id;

    app(UsageRecorder::class)->record([
        'channel' => MeteringChannel::Api->value,
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/products',
        'route_name' => 'api.v1.products.index',
        'resource' => 'products',
        'operation' => 'index',
        'rollup_key' => 'api.v1.products.index',
        'personal_access_token_id' => $tokenId,
        'user_id' => $user->id,
        'ability' => 'products:read',
        'status_code' => 200,
        'duration_ms' => 4,
        'request_bytes' => 0,
        'response_bytes' => 64,
        'price_weight' => 1,
        'weighted_units' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ]);

    expect(RequestLog::query()->sole()->personal_access_token_id)->toBe($tokenId)
        ->and(UsageDaily::query()->sole()->personal_access_token_id)->toBe($tokenId);
});

it('increments existing usage daily rollups instead of overwriting them', function () {
    $recorder = app(UsageRecorder::class);

    $baseEntry = [
        'channel' => MeteringChannel::Api->value,
        'request_id' => (string) Str::uuid(),
        'method' => 'GET',
        'uri' => '/api/v1/products',
        'route_name' => 'api.v1.products.index',
        'resource' => 'products',
        'operation' => 'index',
        'rollup_key' => 'api.v1.products.index',
        'personal_access_token_id' => null,
        'user_id' => null,
        'ability' => 'products:read',
        'status_code' => 200,
        'duration_ms' => 3,
        'request_bytes' => 0,
        'response_bytes' => 64,
        'price_weight' => 1,
        'weighted_units' => 1,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'request_body' => null,
        'response_body' => null,
    ];

    $recorder->record($baseEntry);
    $recorder->record([
        ...$baseEntry,
        'request_id' => (string) Str::uuid(),
    ]);

    $rollup = UsageDaily::query()->sole();

    expect($rollup->request_count)->toBe(2)
        ->and($rollup->weighted_units)->toBe(2)
        ->and(UsageDaily::query()->count())->toBe(1);
});

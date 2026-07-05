<?php

use App\Jobs\ExportApiRequestLog;
use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\RequestLog;
use App\Models\User;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

it('exports api request logs to CSV with cache handoff', function () {
    $user = User::factory()->create();

    RequestLog::factory()->create([
        'channel' => 'api',
        'method' => 'GET',
        'route_name' => 'api.v1.products.index',
        'status_code' => 200,
        'price_weight' => 2,
        'duration_ms' => 45,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'api'))->handle();

    $cacheKey = "api-logs-export:{$user->id}";
    $filename = Cache::get($cacheKey);

    expect($filename)->not->toBeNull();
    Storage::assertExists($filename);

    $content = Storage::get($filename);
    expect($content)
        ->toContain('Method,Route,Status')
        ->toContain('api.v1.products.index')
        ->toContain('GET');
});

it('exports webhook sent logs for the webhook_sent channel', function () {
    $user = User::factory()->create();

    WebhookLog::factory()->create([
        'event' => 'invoice.issued',
        'response_code' => 201,
        'attempts' => 1,
        'price_weight' => 3,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'webhook_sent'))->handle();

    $filename = Cache::get("api-logs-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)
        ->toContain('Event,"Response Code"')
        ->toContain('invoice.issued')
        ->toContain('201');
});

it('exports inbound webhook logs for the webhook_received channel', function () {
    $user = User::factory()->create();
    $source = InboundWebhookSource::factory()->create(['name' => 'Partner Hook']);

    InboundWebhookLog::factory()->for($source, 'inboundWebhookSource')->create([
        'event' => 'order.created',
        'signature_valid' => true,
        'status_code' => 200,
        'price_weight' => 1,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'webhook_received'))->handle();

    $filename = Cache::get("api-logs-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)
        ->toContain('Source,Event')
        ->toContain('Partner Hook')
        ->toContain('order.created');
});

it('applies status class and token filters for api exports', function () {
    $user = User::factory()->create();
    $token = $user->createToken('filtered-token', ['products:read']);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.match',
        'method' => 'GET',
        'status_code' => 404,
        'personal_access_token_id' => $token->accessToken->id,
        'created_at' => now(),
    ]);

    RequestLog::factory()->create([
        'channel' => 'api',
        'route_name' => 'api.v1.other',
        'method' => 'POST',
        'status_code' => 200,
        'created_at' => now(),
    ]);

    (new ExportApiRequestLog($user->id, 'api', [
        'window' => '7d',
        'status_class' => '4xx',
        'token_id' => (string) $token->accessToken->id,
        'method' => 'GET',
    ]))->handle();

    $filename = Cache::get("api-logs-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('api.v1.match');
    expect($content)->not->toContain('api.v1.other');
});

it('marks failed exports in cache', function () {
    $user = User::factory()->create();

    (new ExportApiRequestLog($user->id, 'api'))->failed(new RuntimeException('disk full'));

    expect(Cache::get("api-logs-export:{$user->id}"))->toBe('failed');
});

<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('encrypts and hides inbound webhook source secrets', function () {
    $plainSecret = 'super-secret-webhook-key';

    $source = InboundWebhookSource::factory()->create(['secret' => $plainSecret]);
    $source->refresh();

    expect($source->secret)->toBe($plainSecret)
        ->and($source->toArray())->not->toHaveKey('secret');

    $raw = InboundWebhookSource::query()->findOrFail($source->id)->getAttributes()['secret'];
    expect($raw)->not->toBe($plainSecret);
});

it('casts inbound webhook source expected events as array', function () {
    $events = ['order.created', 'order.cancelled'];

    $source = InboundWebhookSource::factory()->create(['expected_events' => $events]);

    expect($source->expected_events)->toBe($events)
        ->and($source->is_active)->toBeBool()->toBeTrue();
});

it('casts inbound webhook log json columns as arrays', function () {
    $headers = ['X-Test' => 'value'];
    $payload = ['order_id' => 42];

    $log = InboundWebhookLog::factory()->create([
        'headers' => $headers,
        'payload' => $payload,
        'signature_valid' => true,
    ]);

    expect($log->headers)->toBe($headers)
        ->and($log->payload)->toBe($payload)
        ->and($log->signature_valid)->toBeTrue();
});

it('casts request log numeric fields as integers', function () {
    $log = RequestLog::factory()->create([
        'status_code' => 404,
        'duration_ms' => 120,
        'request_bytes' => 512,
        'response_bytes' => 256,
        'price_weight' => 2,
        'weighted_units' => 2,
    ]);

    expect($log->status_code)->toBeInt()->toBe(404)
        ->and($log->duration_ms)->toBeInt()->toBe(120)
        ->and($log->price_weight)->toBeInt()->toBe(2)
        ->and($log->weighted_units)->toBeInt()->toBe(2);
});

it('loads api metering config defaults', function () {
    expect(config('api_metering.logging_enabled'))->toBeTrue()
        ->and(config('api_metering.capture_bodies'))->toBeFalse()
        ->and(config('api_metering.body_max_bytes'))->toBe(4096)
        ->and(config('api_metering.detail_retention_days'))->toBe(90)
        ->and(config('api_metering.redaction'))->toContain('Authorization', 'password', 'secret', 'token');
});

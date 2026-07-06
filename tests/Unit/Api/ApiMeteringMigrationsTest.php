<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\RequestLog;
use App\Models\UsageDaily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates api metering tables with expected columns on sqlite', function () {
    expect(Schema::hasTable('request_logs'))->toBeTrue()
        ->and(Schema::hasColumns('request_logs', [
            'id', 'channel', 'request_id', 'method', 'uri', 'route_name', 'resource',
            'operation', 'personal_access_token_id', 'user_id', 'ability', 'status_code',
            'duration_ms', 'request_bytes', 'response_bytes', 'price_weight', 'weighted_units',
            'ip_address', 'user_agent', 'request_body', 'response_body', 'created_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('usage_daily'))->toBeTrue()
        ->and(Schema::hasColumns('usage_daily', [
            'id', 'dedupe_key', 'date', 'channel', 'personal_access_token_id',
            'inbound_webhook_source_id', 'key', 'resource', 'status_class',
            'request_count', 'weighted_units', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('inbound_webhook_sources'))->toBeTrue()
        ->and(Schema::hasColumns('inbound_webhook_sources', [
            'id', 'name', 'slug', 'description', 'secret', 'signature_algo',
            'signature_header', 'expected_events', 'price_weight', 'is_active',
            'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('inbound_webhook_logs'))->toBeTrue()
        ->and(Schema::hasColumns('inbound_webhook_logs', [
            'id', 'inbound_webhook_source_id', 'event', 'headers', 'payload',
            'signature_valid', 'status_code', 'request_id', 'price_weight',
            'weighted_units', 'created_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('webhook_logs', ['price_weight', 'weighted_units']))->toBeTrue();
});

it('persists request logs via factory without updated_at', function () {
    $log = RequestLog::factory()->create();

    expect($log->exists)->toBeTrue()
        ->and(RequestLog::UPDATED_AT)->toBeNull()
        ->and(Schema::hasColumn('request_logs', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('request_logs', 'created_at'))->toBeTrue();
});

it('persists inbound webhook logs via factory without updated_at', function () {
    $log = InboundWebhookLog::factory()->create();

    expect($log->exists)->toBeTrue()
        ->and(InboundWebhookLog::UPDATED_AT)->toBeNull()
        ->and(Schema::hasColumn('inbound_webhook_logs', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('inbound_webhook_logs', 'created_at'))->toBeTrue();
});

it('persists usage daily rollups via factory', function () {
    $rollup = UsageDaily::factory()->create();

    expect($rollup->exists)->toBeTrue()
        ->and($rollup->dedupe_key)->not->toBeEmpty()
        ->and($rollup->date)->toBeInstanceOf(DateTimeInterface::class);
});

it('persists inbound webhook sources via factory', function () {
    $source = InboundWebhookSource::factory()->create();

    expect($source->exists)->toBeTrue()
        ->and($source->slug)->not->toBeEmpty();
});

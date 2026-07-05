<?php

use App\Models\InboundWebhookLog;
use App\Models\RequestLog;
use App\Models\UsageDaily;

beforeEach(function () {
    config(['api_metering.detail_retention_days' => 90]);
});

it('prunes old request and inbound webhook detail logs while keeping usage daily rollups', function () {
    RequestLog::factory()->create(['created_at' => now()->subDays(120)]);
    RequestLog::factory()->create(['created_at' => now()->subDays(10)]);
    InboundWebhookLog::factory()->create(['created_at' => now()->subDays(120)]);
    InboundWebhookLog::factory()->create(['created_at' => now()->subDays(5)]);
    UsageDaily::factory()->create(['date' => now()->subDays(120)->toDateString()]);

    $this->artisan('api:prune-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 2 detail log entries');

    expect(RequestLog::query()->count())->toBe(1)
        ->and(InboundWebhookLog::query()->count())->toBe(1)
        ->and(UsageDaily::query()->count())->toBe(1);
});

it('reports zero when nothing is older than the retention window', function () {
    RequestLog::factory()->create(['created_at' => now()]);
    InboundWebhookLog::factory()->create(['created_at' => now()]);

    $this->artisan('api:prune-logs')
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 detail log entries');

    expect(RequestLog::query()->count())->toBe(1)
        ->and(InboundWebhookLog::query()->count())->toBe(1);
});

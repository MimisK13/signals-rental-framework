<?php

use App\Jobs\ExportApiUsage;
use App\Models\UsageDaily;
use App\Models\User;
use App\Queries\Api\UsageQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    Storage::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exports usage aggregates to CSV and caches the filename', function () {
    $user = User::factory()->create();

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 10,
        'weighted_units' => 10,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'key' => 'api.v1.members.index',
        'status_class' => '2xx',
        'request_count' => 5,
        'weighted_units' => 5,
    ]);

    (new ExportApiUsage($user->id, [
        'dimension' => 'endpoint',
        'window' => '7d',
    ]))->handle(app(UsageQuery::class));

    $cacheKey = "api-usage-export:{$user->id}";
    $filename = Cache::get($cacheKey);

    expect($filename)->not->toBeNull();
    Storage::assertExists($filename);

    $content = Storage::get($filename);
    expect($content)->toContain('Key,Label,"Request Count","Weighted Units"')
        ->toContain('api.v1.products.index')
        ->toContain('api.v1.members.index')
        ->toContain('10')
        ->toContain('5');
});

it('applies export filters', function () {
    $user = User::factory()->create();

    UsageDaily::factory()->create([
        'date' => '2026-06-01',
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 4,
        'weighted_units' => 4,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-05-01',
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 99,
        'weighted_units' => 99,
    ]);

    (new ExportApiUsage($user->id, [
        'dimension' => 'endpoint',
        'window' => 'custom',
        'from' => '2026-06-01',
        'to' => '2026-06-01',
    ]))->handle(app(UsageQuery::class));

    $filename = Cache::get("api-usage-export:{$user->id}");
    $content = Storage::get($filename);

    expect($content)->toContain('4');
    expect($content)->not->toContain('99');
});

it('sets cache to failed when the job fails', function () {
    $user = User::factory()->create();

    $job = new ExportApiUsage($user->id);
    $job->failed(new RuntimeException('Export failed'));

    expect(Cache::get("api-usage-export:{$user->id}"))->toBe('failed');
});

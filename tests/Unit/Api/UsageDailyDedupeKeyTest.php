<?php

use App\Enums\MeteringChannel;
use App\Models\UsageDaily;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('computes a stable dedupe key from rollup dimensions', function () {
    $date = '2026-07-01';
    $channel = MeteringChannel::Api->value;
    $key = 'api.v1.products.index';

    $first = UsageDaily::computeDedupeKey($date, $channel, $key, null, null, '2xx');
    $second = UsageDaily::computeDedupeKey(Carbon::parse($date), $channel, $key, null, null, '2xx');

    expect($first)->toBe($second)
        ->and($first)->not->toBe(
            UsageDaily::computeDedupeKey($date, $channel, $key, null, null, '4xx'),
        );
});

it('rejects duplicate dedupe keys for the same logical rollup', function () {
    $attributes = [
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.members.index',
        'resource' => 'members',
        'status_class' => '2xx',
        'request_count' => 1,
        'weighted_units' => 1,
    ];

    UsageDaily::factory()->create($attributes);

    expect(fn () => UsageDaily::factory()->create($attributes))
        ->toThrow(QueryException::class);
});

it('allows different logical keys to coexist', function () {
    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.members.index',
        'status_class' => '2xx',
    ]);

    expect(UsageDaily::query()->count())->toBe(2);
});

it('increments counters via updateOrCreate on dedupe_key', function () {
    $dedupeKey = UsageDaily::computeDedupeKey(
        '2026-07-01',
        MeteringChannel::Api->value,
        'api.v1.products.index',
        null,
        null,
        '2xx',
    );

    UsageDaily::query()->updateOrCreate(
        ['dedupe_key' => $dedupeKey],
        [
            'date' => '2026-07-01',
            'channel' => MeteringChannel::Api->value,
            'key' => 'api.v1.products.index',
            'resource' => 'products',
            'status_class' => '2xx',
            'request_count' => 1,
            'weighted_units' => 1,
        ],
    );

    $rollup = UsageDaily::query()->updateOrCreate(
        ['dedupe_key' => $dedupeKey],
        [
            'date' => '2026-07-01',
            'channel' => MeteringChannel::Api->value,
            'key' => 'api.v1.products.index',
            'resource' => 'products',
            'status_class' => '2xx',
        ],
    );

    $rollup->increment('request_count', 3);
    $rollup->increment('weighted_units', 3);

    $rollup = UsageDaily::query()->where('dedupe_key', $dedupeKey)->first();

    expect($rollup)->not->toBeNull()
        ->and($rollup->request_count)->toBe(4)
        ->and($rollup->weighted_units)->toBe(4)
        ->and(UsageDaily::query()->count())->toBe(1);
});

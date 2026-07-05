<?php

use App\Enums\MeteringChannel;
use App\Models\UsageDaily;
use App\Models\User;
use App\Queries\Api\UsageQuery;
use App\Support\DateWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->window = DateWindow::fromRequest('7d');
    $this->query = app(UsageQuery::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('aggregates endpoint dimension rows and totals', function () {
    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'key' => 'api.v1.products.index',
        'request_count' => 10,
        'weighted_units' => 10,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'key' => 'api.v1.members.index',
        'request_count' => 5,
        'weighted_units' => 5,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-05-01',
        'key' => 'api.v1.products.index',
        'request_count' => 100,
        'weighted_units' => 100,
    ]);

    $result = ($this->query)($this->window, 'endpoint');

    expect($result->totals)->toBe([
        'request_count' => 15,
        'weighted_units' => 15,
    ]);

    expect($result->rows)->toHaveCount(2);

    $products = collect($result->rows)->firstWhere('key', 'api.v1.products.index');
    expect($products['request_count'])->toBe(10)
        ->and($products['weighted_units'])->toBe(10);
});

it('aggregates token dimension with token labels', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('billing-token');
    $tokenId = PersonalAccessToken::findToken($tokenResult->plainTextToken)->id;

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'personal_access_token_id' => $tokenId,
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 7,
        'weighted_units' => 14,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'personal_access_token_id' => null,
        'key' => 'api.v1.members.index',
        'status_class' => '4xx',
        'request_count' => 3,
        'weighted_units' => 3,
    ]);

    $result = ($this->query)($this->window, 'token');

    expect($result->totals['request_count'])->toBe(10);

    $tokenRow = collect($result->rows)->firstWhere('key', (string) $tokenId);
    expect($tokenRow['label'])->toBe('billing-token')
        ->and($tokenRow['request_count'])->toBe(7);

    $nullRow = collect($result->rows)->firstWhere('key', 'null');
    expect($nullRow['label'])->toBeNull()
        ->and($nullRow['request_count'])->toBe(3);
});

it('aggregates day dimension as a time series', function () {
    UsageDaily::factory()->create([
        'date' => '2026-06-29',
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 2,
        'weighted_units' => 2,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'key' => 'api.v1.members.index',
        'status_class' => '2xx',
        'request_count' => 4,
        'weighted_units' => 8,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-07-01',
        'key' => 'api.v1.usage.index',
        'status_class' => '2xx',
        'request_count' => 1,
        'weighted_units' => 1,
    ]);

    $result = ($this->query)($this->window, 'day');

    expect($result->totals['request_count'])->toBe(7)
        ->and($result->rows)->toHaveCount(3);

    expect(collect($result->rows)->pluck('key')->all())->toContain('2026-06-29', '2026-06-30', '2026-07-01');
});

it('filters by channel', function () {
    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'channel' => MeteringChannel::Api->value,
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 5,
        'weighted_units' => 5,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'channel' => MeteringChannel::WebhookSent->value,
        'key' => 'webhook.delivery',
        'status_class' => '2xx',
        'request_count' => 9,
        'weighted_units' => 9,
    ]);

    $result = ($this->query)($this->window, 'endpoint', MeteringChannel::WebhookSent);

    expect($result->totals['request_count'])->toBe(9)
        ->and($result->rows)->toHaveCount(1);
});

it('filters by personal access token id', function () {
    $user = User::factory()->create();
    $token = PersonalAccessToken::findToken($user->createToken('one')->plainTextToken);
    $otherToken = PersonalAccessToken::findToken($user->createToken('two')->plainTextToken);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'personal_access_token_id' => $token->id,
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 4,
        'weighted_units' => 4,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'personal_access_token_id' => $otherToken->id,
        'key' => 'api.v1.members.index',
        'status_class' => '2xx',
        'request_count' => 6,
        'weighted_units' => 6,
    ]);

    $result = ($this->query)($this->window, 'endpoint', null, $token->id);

    expect($result->totals['request_count'])->toBe(4)
        ->and($result->rows)->toHaveCount(1);
});

it('aggregates resource and status dimensions', function () {
    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'resource' => 'products',
        'key' => 'api.v1.products.index',
        'status_class' => '2xx',
        'request_count' => 3,
        'weighted_units' => 3,
    ]);

    UsageDaily::factory()->create([
        'date' => '2026-06-30',
        'resource' => 'members',
        'key' => 'api.v1.members.index',
        'status_class' => '4xx',
        'request_count' => 2,
        'weighted_units' => 2,
    ]);

    $resourceResult = ($this->query)($this->window, 'resource');
    expect(collect($resourceResult->rows)->pluck('key')->all())->toContain('products', 'members');

    $statusResult = ($this->query)($this->window, 'status');
    expect(collect($statusResult->rows)->pluck('key')->all())->toContain('2xx', '4xx');
});

it('returns zero totals when no rows match', function () {
    $result = ($this->query)($this->window, 'endpoint');

    expect($result->totals)->toBe([
        'request_count' => 0,
        'weighted_units' => 0,
    ])->and($result->rows)->toBe([]);
});

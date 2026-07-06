<?php

use App\Enums\MeteringChannel;
use App\Jobs\ExportApiUsage;
use App\Models\RequestLog;
use App\Models\UsageDaily;
use App\Models\User;
use App\Services\Api\ApiEndpointCatalogue;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
        'api_metering.logging_enabled' => true,
        'api_metering.buffered' => false,
    ]);

    Carbon::setTestNow('2026-07-01 12:00:00');
    $this->seed(PermissionSeeder::class);
    $this->owner = User::factory()->owner()->create();

    $this->mock(ApiEndpointCatalogue::class, function ($mock): void {
        $mock->shouldReceive('get')->andReturn([
            'resource' => 'usage',
            'operation' => 'index',
            'required_ability' => 'api-usage:read',
        ]);
    });
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('GET /api/v1/usage', function () {
    it('returns grouped usage aggregates with meta', function () {
        UsageDaily::factory()->create([
            'date' => '2026-06-30',
            'key' => 'api.v1.products.index',
            'request_count' => 12,
            'weighted_units' => 12,
        ]);

        $token = $this->owner->createToken('test', ['api-usage:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage?dimension=endpoint&window=7d')
            ->assertOk()
            ->assertJsonStructure([
                'usage' => [
                    '*' => ['key', 'label', 'request_count', 'weighted_units'],
                ],
                'meta' => [
                    'totals' => ['request_count', 'weighted_units'],
                    'window' => ['preset', 'from', 'to'],
                    'dimension',
                ],
            ])
            ->assertJsonPath('meta.dimension', 'endpoint')
            ->assertJsonPath('meta.totals.request_count', 12)
            ->assertJsonPath('usage.0.key', 'api.v1.products.index');
    });

    it('respects dimension window channel and token filters', function () {
        $tokenResult = $this->owner->createToken('filtered', ['api-usage:read']);
        $token = PersonalAccessToken::findToken($tokenResult->plainTextToken);

        UsageDaily::factory()->create([
            'date' => '2026-06-01',
            'channel' => MeteringChannel::Api->value,
            'personal_access_token_id' => $token->id,
            'key' => 'api.v1.products.index',
            'status_class' => '2xx',
            'request_count' => 4,
            'weighted_units' => 4,
        ]);

        UsageDaily::factory()->create([
            'date' => '2026-06-01',
            'channel' => MeteringChannel::WebhookSent->value,
            'personal_access_token_id' => $token->id,
            'key' => 'webhook.delivery',
            'status_class' => '2xx',
            'request_count' => 9,
            'weighted_units' => 9,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
            ->getJson('/api/v1/usage?window=custom&from=2026-06-01&to=2026-06-01&channel=webhook_sent&dimension=endpoint&token_id='.$token->id)
            ->assertOk()
            ->assertJsonPath('meta.totals.request_count', 9)
            ->assertJsonCount(1, 'usage');
    });

    it('requires api-usage:read ability', function () {
        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage')
            ->assertForbidden();
    });

    it('requires api.usage.view permission for non-owner users', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api-usage:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage')
            ->assertForbidden();
    });

    it('allows users with api.usage.view permission', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('api.access', 'api.usage.view');
        $token = $user->createToken('test', ['api-usage:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage')
            ->assertOk();
    });

    it('logs the request itself at weight zero', function () {
        $tokenResult = $this->owner->createToken('test', ['api-usage:read']);

        $this->withHeader('Authorization', 'Bearer '.$tokenResult->plainTextToken)
            ->getJson('/api/v1/usage')
            ->assertOk();

        $log = RequestLog::query()->where('route_name', 'api.v1.usage.index')->sole();

        expect($log->price_weight)->toBe(0)
            ->and($log->weighted_units)->toBe(0);
    });
});

describe('GET /api/v1/usage/export', function () {
    it('returns 202 and queues ExportApiUsage', function () {
        Queue::fake();

        $token = $this->owner->createToken('test', ['api-usage:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage/export?dimension=day&window=30d')
            ->assertAccepted()
            ->assertJsonPath('message', 'Accepted');

        Queue::assertPushed(ExportApiUsage::class, function (ExportApiUsage $job): bool {
            return $job->userId === $this->owner->id
                && $job->filters['dimension'] === 'day'
                && $job->filters['window'] === '30d';
        });
    });

    it('requires api-usage:read ability for export', function () {
        Queue::fake();

        $token = $this->owner->createToken('test', ['users:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/usage/export')
            ->assertForbidden();

        Queue::assertNothingPushed();
    });
});

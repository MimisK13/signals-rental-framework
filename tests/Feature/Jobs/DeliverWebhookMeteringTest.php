<?php

use App\Enums\MeteringChannel;
use App\Jobs\DeliverWebhook;
use App\Models\UsageDaily;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\Api\WebhookEventRegistry;
use Illuminate\Support\Facades\Http;

it('meters outbound webhook delivery on the first attempt only', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create();
    $registry = app(WebhookEventRegistry::class);
    $registry->register('user.created', ['weight' => 3, 'group' => 'user']);

    (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();

    $log = WebhookLog::query()->sole();

    expect($log->price_weight)->toBe(3)
        ->and($log->weighted_units)->toBe(3);

    $rollup = UsageDaily::query()->sole();

    expect($rollup->channel)->toBe(MeteringChannel::WebhookSent->value)
        ->and($rollup->key)->toBe('user.created')
        ->and($rollup->resource)->toBe('user')
        ->and($rollup->personal_access_token_id)->toBeNull()
        ->and($rollup->inbound_webhook_source_id)->toBeNull()
        ->and($rollup->status_class)->toBe('2xx')
        ->and($rollup->request_count)->toBe(1)
        ->and($rollup->weighted_units)->toBe(3);
});

it('does not meter outbound webhook retries', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $webhook = Webhook::factory()->create();
    $job = new class($webhook, 'user.created', ['id' => 1]) extends DeliverWebhook
    {
        public function attempts(): int
        {
            return 2;
        }
    };

    $job->handle();

    expect(WebhookLog::query()->count())->toBe(1)
        ->and(UsageDaily::query()->count())->toBe(0)
        ->and(WebhookLog::query()->value('price_weight'))->toBe(0)
        ->and(WebhookLog::query()->value('weighted_units'))->toBe(0);
});

it('meters failed outbound deliveries using the response status class', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $webhook = Webhook::factory()->create(['consecutive_failures' => 0]);

    try {
        (new DeliverWebhook($webhook, 'user.created', ['id' => 1]))->handle();
    } catch (RuntimeException) {
        // Expected for retryable server errors.
    }

    expect(UsageDaily::query()->sole()->status_class)->toBe('5xx')
        ->and(UsageDaily::query()->sole()->request_count)->toBe(1);
});

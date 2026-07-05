<?php

use App\Enums\MeteringChannel;
use App\Events\InboundWebhookReceived;
use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\UsageDaily;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
        'api_metering.capture_bodies' => false,
        'api_metering.buffered' => false,
    ]);
});

/**
 * @param  array<string, mixed>  $sourceAttributes
 * @param  array<string, mixed>  $payload
 * @return array{source: InboundWebhookSource, payload: string}
 */
function inboundWebhookFixture(array $sourceAttributes = [], array $payload = []): array
{
    $source = InboundWebhookSource::factory()->create($sourceAttributes);
    $payloadString = json_encode($payload === [] ? ['event' => 'order.created', 'id' => 123] : $payload, JSON_THROW_ON_ERROR);

    return [
        'source' => $source,
        'payload' => $payloadString,
    ];
}

function signInboundWebhook(InboundWebhookSource $source, string $payload): string
{
    return hash_hmac($source->signature_algo, $payload, $source->secret);
}

/**
 * @param  array<string, string>  $extraHeaders
 * @return array<string, string>
 */
function webhookServerHeaders(array $extraHeaders = []): array
{
    $headers = array_merge([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ], $extraHeaders);

    $server = [];

    foreach ($headers as $key => $value) {
        $normalisedKey = strtr(strtoupper($key), '-', '_');

        if (! in_array($normalisedKey, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $normalisedKey = 'HTTP_'.$normalisedKey;
        }

        $server[$normalisedKey] = $value;
    }

    return $server;
}

/**
 * @param  array<string, string>  $extraHeaders
 * @return TestResponse<Response>
 */
function postInboundWebhook(InboundWebhookSource $source, string $payload, array $extraHeaders = []): TestResponse
{
    $headers = array_merge([
        $source->signature_header => signInboundWebhook($source, $payload),
    ], $extraHeaders);

    return call(
        'POST',
        route('webhooks.receive', $source->slug),
        [],
        [],
        [],
        webhookServerHeaders($headers),
        $payload,
    );
}

it('accepts a valid signature and meters usage', function () {
    Event::fake([InboundWebhookReceived::class]);

    ['source' => $source, 'payload' => $payload] = inboundWebhookFixture();

    postInboundWebhook($source, $payload, ['X-Request-Id' => 'req-valid-123'])
        ->assertOk()
        ->assertJson(['status' => 'received']);

    $log = InboundWebhookLog::query()->sole();

    expect($log->inbound_webhook_source_id)->toBe($source->id)
        ->and($log->signature_valid)->toBeTrue()
        ->and($log->event)->toBe('order.created')
        ->and($log->status_code)->toBe(200)
        ->and($log->request_id)->toBe('req-valid-123')
        ->and($log->price_weight)->toBe(1)
        ->and($log->weighted_units)->toBe(1)
        ->and($log->headers)->toBeNull()
        ->and($log->payload)->toBeNull();

    $rollup = UsageDaily::query()->sole();

    expect($rollup->channel)->toBe(MeteringChannel::WebhookReceived->value)
        ->and($rollup->key)->toBe($source->slug)
        ->and($rollup->inbound_webhook_source_id)->toBe($source->id)
        ->and($rollup->personal_access_token_id)->toBeNull()
        ->and($rollup->resource)->toBe($source->slug)
        ->and($rollup->status_class)->toBe('2xx')
        ->and($rollup->request_count)->toBe(1)
        ->and($rollup->weighted_units)->toBe(1);

    Event::assertDispatched(InboundWebhookReceived::class, function (InboundWebhookReceived $event) use ($source, $log): bool {
        return $event->source->is($source)
            && $event->payload === ['event' => 'order.created', 'id' => 123]
            && $event->logId === $log->id;
    });
});

it('rejects an invalid signature without metering or dispatching an event', function () {
    Event::fake([InboundWebhookReceived::class]);

    ['source' => $source, 'payload' => $payload] = inboundWebhookFixture();

    call(
        'POST',
        route('webhooks.receive', $source->slug),
        [],
        [],
        [],
        webhookServerHeaders([
            $source->signature_header => 'not-a-valid-signature',
        ]),
        $payload,
    )
        ->assertUnauthorized()
        ->assertJson(['message' => 'Invalid signature']);

    $log = InboundWebhookLog::query()->sole();

    expect($log->signature_valid)->toBeFalse()
        ->and($log->status_code)->toBe(401)
        ->and($log->price_weight)->toBe(0)
        ->and($log->weighted_units)->toBe(0);

    expect(UsageDaily::query()->count())->toBe(0);

    Event::assertNotDispatched(InboundWebhookReceived::class);
});

it('honours the source price weight for valid signatures', function () {
    Event::fake([InboundWebhookReceived::class]);

    ['source' => $source, 'payload' => $payload] = inboundWebhookFixture(['price_weight' => 3]);

    postInboundWebhook($source, $payload)->assertOk();

    $log = InboundWebhookLog::query()->sole();

    expect($log->price_weight)->toBe(3)
        ->and($log->weighted_units)->toBe(3);

    $rollup = UsageDaily::query()->sole();

    expect($rollup->weighted_units)->toBe(3)
        ->and($rollup->request_count)->toBe(1);
});

it('stores null headers and payload when body capture is disabled', function () {
    config(['api_metering.capture_bodies' => false]);

    ['source' => $source, 'payload' => $payload] = inboundWebhookFixture();

    postInboundWebhook($source, $payload)->assertOk();

    $log = InboundWebhookLog::query()->sole();

    expect($log->headers)->toBeNull()
        ->and($log->payload)->toBeNull();
});

it('stores redacted headers and payload when body capture is enabled', function () {
    config([
        'api_metering.capture_bodies' => true,
        'api_metering.body_max_bytes' => 8192,
    ]);

    $source = InboundWebhookSource::factory()->create();
    $payload = json_encode([
        'event' => 'order.created',
        'secret' => 'super-secret-value',
    ], JSON_THROW_ON_ERROR);

    postInboundWebhook($source, $payload, [
        'X-Signals-Signature' => 'sensitive-token',
    ])->assertOk();

    $log = InboundWebhookLog::query()->sole();

    expect($log->headers)->toBeArray()
        ->and($log->headers['x-signals-signature'] ?? $log->headers['X-Signals-Signature'] ?? null)->toBe('[REDACTED]')
        ->and($log->payload)->toBeArray()
        ->and($log->payload['secret'])->toBe('[REDACTED]');
});

it('truncates captured payload when it exceeds the configured byte limit', function () {
    config([
        'api_metering.capture_bodies' => true,
        'api_metering.body_max_bytes' => 64,
    ]);

    $source = InboundWebhookSource::factory()->create();
    $payload = json_encode([
        'event' => 'order.created',
        'description' => str_repeat('x', 200),
    ], JSON_THROW_ON_ERROR);

    postInboundWebhook($source, $payload)->assertOk();

    $log = InboundWebhookLog::query()->sole();

    expect($log->payload)->toBeArray();

    $encodedPayload = json_encode($log->payload);
    $isTruncated = strlen($encodedPayload) <= 64 || array_key_exists('raw', $log->payload);

    expect($isTruncated)->toBeTrue();
});

it('accepts webhook posts without a csrf token', function () {
    Event::fake([InboundWebhookReceived::class]);

    ['source' => $source, 'payload' => $payload] = inboundWebhookFixture();

    postInboundWebhook($source, $payload)
        ->assertOk()
        ->assertJson(['status' => 'received']);

    expect(InboundWebhookLog::query()->count())->toBe(1);
});

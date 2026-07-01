<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;

beforeEach(function () {
    config([
        'signals.installed' => true,
        'signals.setup_complete' => true,
    ]);
});

/**
 * @param  array<string, string>  $extraHeaders
 * @return array<string, string>
 */
function middlewareWebhookServerHeaders(array $extraHeaders = []): array
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

it('returns 404 for an unknown source slug', function () {
    $payload = '{"event":"order.created"}';

    $this->call(
        'POST',
        route('webhooks.receive', 'missing-source'),
        [],
        [],
        [],
        middlewareWebhookServerHeaders(),
        $payload,
    )->assertNotFound();

    expect(InboundWebhookLog::query()->count())->toBe(0);
});

it('returns 404 for an inactive source slug', function () {
    $source = InboundWebhookSource::factory()->inactive()->create();
    $payload = '{"event":"order.created"}';

    $this->call(
        'POST',
        route('webhooks.receive', $source->slug),
        [],
        [],
        [],
        middlewareWebhookServerHeaders([
            $source->signature_header => hash_hmac($source->signature_algo, $payload, $source->secret),
        ]),
        $payload,
    )->assertNotFound();

    expect(InboundWebhookLog::query()->count())->toBe(0);
});

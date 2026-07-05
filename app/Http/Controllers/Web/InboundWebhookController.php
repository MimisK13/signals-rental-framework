<?php

namespace App\Http\Controllers\Web;

use App\Enums\MeteringChannel;
use App\Events\InboundWebhookReceived;
use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Services\Api\UsageRecorder;
use App\Support\Api\MeteringBodyRedactor;
use App\Support\Api\MeteringStatusClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InboundWebhookController
{
    public function __construct(
        private readonly UsageRecorder $usageRecorder,
        private readonly MeteringBodyRedactor $bodyRedactor,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var InboundWebhookSource $source */
        $source = $request->attributes->get('inbound_webhook_source');
        $signatureValid = (bool) $request->attributes->get('inbound_signature_valid');
        $decodedPayload = $this->decodePayload($request);
        $priceWeight = $signatureValid ? $source->price_weight : 0;
        $statusCode = $signatureValid ? 200 : 401;
        $requestId = $this->resolveRequestId($request);
        $logId = 0;

        try {
            $log = InboundWebhookLog::query()->create([
                'inbound_webhook_source_id' => $source->id,
                'event' => $this->resolveEvent($request, $decodedPayload),
                'headers' => $this->captureHeaders($request),
                'payload' => $this->capturePayload($request),
                'signature_valid' => $signatureValid,
                'status_code' => $statusCode,
                'request_id' => $requestId,
                'price_weight' => $priceWeight,
                'weighted_units' => $priceWeight,
            ]);

            $logId = $log->id;

            if ($signatureValid) {
                $this->usageRecorder->incrementDailyUsage([
                    'channel' => MeteringChannel::WebhookReceived->value,
                    'key' => $source->slug,
                    'inbound_webhook_source_id' => $source->id,
                    'personal_access_token_id' => null,
                    'resource' => $source->slug,
                    'status_class' => MeteringStatusClass::fromStatusCode($statusCode),
                ], 1, $priceWeight);
            }
        } catch (\Throwable $exception) {
            $this->usageRecorder->reportFailure($exception);
        }

        if ($signatureValid) {
            InboundWebhookReceived::dispatch($source, $decodedPayload ?? [], $logId);

            return response()->json(['status' => 'received']);
        }

        return response()->json(['message' => 'Invalid signature'], 401);
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->header('X-Request-Id');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function resolveEvent(Request $request, ?array $payload): ?string
    {
        if (is_array($payload)) {
            foreach (['event', 'type', 'action'] as $key) {
                $value = $payload[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        foreach (['X-Event', 'X-Event-Type', 'X-Webhook-Event'] as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(Request $request): ?array
    {
        $content = $request->getContent();

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function captureHeaders(Request $request): ?array
    {
        if (! config('api_metering.capture_bodies')) {
            return null;
        }

        /** @var array<string, list<string|null>> $headers */
        $headers = $request->headers->all();
        $normalised = [];

        foreach ($headers as $key => $values) {
            $normalised[$key] = count($values) === 1 ? ($values[0] ?? '') : $values;
        }

        $redacted = $this->bodyRedactor->redactAndTruncate(
            json_encode($normalised, JSON_THROW_ON_ERROR),
            config('api_metering.redaction', []),
            (int) config('api_metering.body_max_bytes'),
        );

        if ($redacted === null) {
            return null;
        }

        $decoded = $this->decodeRedactedJson($redacted);

        return $decoded ?? ['raw' => $redacted];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function capturePayload(Request $request): ?array
    {
        if (! config('api_metering.capture_bodies')) {
            return null;
        }

        $redacted = $this->bodyRedactor->redactAndTruncate(
            $request->getContent(),
            config('api_metering.redaction', []),
            (int) config('api_metering.body_max_bytes'),
        );

        if ($redacted === null) {
            return null;
        }

        $decoded = $this->decodeRedactedJson($redacted);

        return $decoded ?? ['raw' => $redacted];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeRedactedJson(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}

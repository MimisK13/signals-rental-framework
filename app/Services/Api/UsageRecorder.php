<?php

namespace App\Services\Api;

use App\Enums\MeteringChannel;
use App\Models\RequestLog;
use App\Models\UsageDaily;
use App\Support\Api\MeteringStatusClass;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\PersonalAccessToken;

class UsageRecorder
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function record(array $entry): void
    {
        if ($this->shouldBuffer()) {
            $this->pushToBuffer($entry);

            return;
        }

        $this->persistEntries([$entry]);
    }

    public function flush(int $max = 1000): int
    {
        $entries = $this->drainBuffer($max);

        if ($entries === []) {
            return 0;
        }

        $this->persistEntries($entries);

        return count($entries);
    }

    /**
     * @param  array{
     *     date?: Carbon|string,
     *     channel: string,
     *     key: string,
     *     resource?: string|null,
     *     status_class: string,
     *     personal_access_token_id?: int|null,
     *     inbound_webhook_source_id?: int|null,
     *     request_count?: int,
     *     weighted_units?: int,
     * }  $dimensions
     */
    public function incrementDailyUsage(array $dimensions, int $requestCount = 1, int $weightedUnits = 1): void
    {
        $date = $dimensions['date'] ?? now()->toDateString();
        $dateString = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        $channel = $dimensions['channel'];
        $key = $dimensions['key'];
        $personalAccessTokenId = $dimensions['personal_access_token_id'] ?? null;
        $inboundWebhookSourceId = $dimensions['inbound_webhook_source_id'] ?? null;
        $statusClass = $dimensions['status_class'];

        $dedupeKey = UsageDaily::computeDedupeKey(
            $dateString,
            $channel,
            $key,
            $personalAccessTokenId,
            $inboundWebhookSourceId,
            $statusClass,
        );

        $rollup = UsageDaily::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'date' => $dateString,
                'channel' => $channel,
                'personal_access_token_id' => $personalAccessTokenId,
                'inbound_webhook_source_id' => $inboundWebhookSourceId,
                'key' => $key,
                'resource' => $dimensions['resource'] ?? null,
                'status_class' => $statusClass,
                'request_count' => 0,
                'weighted_units' => 0,
            ],
        );

        $rollup->increment('request_count', $requestCount);
        $rollup->increment('weighted_units', $weightedUnits);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function persistEntries(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $entries = $this->nullifyMissingTokenReferences($entries);

        $now = now();
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = $this->requestLogAttributes($entry, $now);

            $this->incrementDailyUsage([
                'date' => $entry['recorded_at'] ?? $now,
                'channel' => (string) ($entry['channel'] ?? MeteringChannel::Api->value),
                'key' => (string) ($entry['rollup_key'] ?? $entry['route_name'] ?? 'unknown'),
                'resource' => $entry['resource'] ?? null,
                'status_class' => MeteringStatusClass::fromStatusCode((int) ($entry['status_code'] ?? 0)),
                'personal_access_token_id' => $entry['personal_access_token_id'] ?? null,
                'inbound_webhook_source_id' => $entry['inbound_webhook_source_id'] ?? null,
            ], 1, (int) ($entry['weighted_units'] ?? 0));
        }

        RequestLog::query()->insert($rows);
    }

    /**
     * Metering rows are written after the response has returned — and, when
     * buffering is enabled, up to a minute later by the `api:flush-logs`
     * command. A referenced token can be deleted in that window; the API
     * Explorer, for instance, mints an ephemeral token per request and revokes
     * it the moment the round-trip completes. Both `request_logs` and
     * `usage_daily` foreign-key `personal_access_token_id` to
     * `personal_access_tokens`, so a stale reference would make the entire
     * insert fail and the request would silently vanish from the logs. Null out
     * any token references that no longer exist so the request is still
     * recorded — attribution simply falls back to "no token", mirroring the
     * columns' own `nullOnDelete` semantics.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function nullifyMissingTokenReferences(array $entries): array
    {
        $tokenIds = [];

        foreach ($entries as $entry) {
            $tokenId = $entry['personal_access_token_id'] ?? null;

            if ($tokenId !== null) {
                $tokenIds[(int) $tokenId] = true;
            }
        }

        if ($tokenIds === []) {
            return $entries;
        }

        $existing = array_flip(
            PersonalAccessToken::query()
                ->whereIn('id', array_keys($tokenIds))
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
        );

        foreach ($entries as $index => $entry) {
            $tokenId = $entry['personal_access_token_id'] ?? null;

            if ($tokenId !== null && ! isset($existing[(int) $tokenId])) {
                $entries[$index]['personal_access_token_id'] = null;
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function requestLogAttributes(array $entry, Carbon|CarbonInterface $now): array
    {
        return [
            'channel' => (string) ($entry['channel'] ?? MeteringChannel::Api->value),
            'request_id' => (string) $entry['request_id'],
            'method' => (string) $entry['method'],
            'uri' => (string) $entry['uri'],
            'route_name' => $entry['route_name'] ?? null,
            'resource' => $entry['resource'] ?? null,
            'operation' => $entry['operation'] ?? null,
            'personal_access_token_id' => $entry['personal_access_token_id'] ?? null,
            'user_id' => $entry['user_id'] ?? null,
            'ability' => $entry['ability'] ?? null,
            'status_code' => (int) $entry['status_code'],
            'duration_ms' => (int) $entry['duration_ms'],
            'request_bytes' => (int) ($entry['request_bytes'] ?? 0),
            'response_bytes' => (int) ($entry['response_bytes'] ?? 0),
            'price_weight' => (int) ($entry['price_weight'] ?? 1),
            'weighted_units' => (int) ($entry['weighted_units'] ?? 0),
            'ip_address' => $entry['ip_address'] ?? null,
            'user_agent' => $entry['user_agent'] ?? null,
            'request_body' => $entry['request_body'] ?? null,
            'response_body' => $entry['response_body'] ?? null,
            'created_at' => $entry['recorded_at'] ?? $now,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function pushToBuffer(array $entry): void
    {
        $encoded = json_encode($entry, JSON_THROW_ON_ERROR);

        Redis::connection()->lpush(
            (string) config('api_metering.buffer_key'),
            $encoded,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function drainBuffer(int $max): array
    {
        $bufferKey = (string) config('api_metering.buffer_key');
        $entries = [];

        for ($index = 0; $index < $max; $index++) {
            $raw = Redis::connection()->rpop($bufferKey);

            if (in_array($raw, [null, false], true)) {
                break;
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
            $entries[] = $decoded;
        }

        return $entries;
    }

    private function shouldBuffer(): bool
    {
        if (! config('api_metering.buffered')) {
            return false;
        }

        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function reportFailure(\Throwable $exception): void
    {
        $channel = config('api_metering.log_channel');

        if (is_string($channel) && $channel !== '') {
            Log::channel($channel)->warning('API metering capture failed', [
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        report($exception);
    }
}

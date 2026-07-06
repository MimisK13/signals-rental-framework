<?php

namespace App\Services\Api;

use App\Models\ApiIdempotencyKey;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyKeyService
{
    public const REPLAY_HEADER = 'Idempotency-Replayed';

    /**
     * @return array{outcome: 'process', record: ApiIdempotencyKey}|array{outcome: 'replay', record: ApiIdempotencyKey}|array{outcome: 'conflict'}|array{outcome: 'in_flight'}
     */
    public function claim(string $key, int $tokenId, string $fingerprint): array
    {
        $now = now();
        $expiresAt = $now->copy()->addHours(max((int) config('idempotency.ttl_hours', 24), 1));

        /** @var object{id: int|string}|null $inserted */
        $inserted = ApiIdempotencyKey::query()->getConnection()->selectOne(
            'insert into api_idempotency_keys (key, personal_access_token_id, request_fingerprint, expires_at, created_at)
             values (?, ?, ?, ?, ?)
             on conflict (personal_access_token_id, key) do nothing
             returning id',
            [$key, $tokenId, $fingerprint, $expiresAt, $now],
        );

        if ($inserted !== null) {
            $record = ApiIdempotencyKey::query()->findOrFail((int) $inserted->id);

            return ['outcome' => 'process', 'record' => $record];
        }

        return $this->resolveExistingClaim($key, $tokenId, $fingerprint);
    }

    /**
     * @return array{outcome: 'replay', record: ApiIdempotencyKey}|array{outcome: 'conflict'}|array{outcome: 'in_flight'}
     */
    public function resolveExistingClaim(string $key, int $tokenId, string $fingerprint): array
    {
        /** @var ApiIdempotencyKey|null $existing */
        $existing = ApiIdempotencyKey::query()
            ->where('personal_access_token_id', $tokenId)
            ->where('key', $key)
            ->first();

        if ($existing === null) {
            return ['outcome' => 'in_flight'];
        }

        if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
            return ['outcome' => 'conflict'];
        }

        if ($existing->isInFlight()) {
            return ['outcome' => 'in_flight'];
        }

        return ['outcome' => 'replay', 'record' => $existing];
    }

    public function fingerprint(Request $request): string
    {
        $payload = $this->normalizePayload($request->all());

        $material = strtoupper($request->method())
            ."\n"
            .$request->path()
            ."\n"
            .json_encode($payload, JSON_THROW_ON_ERROR);

        return hash('sha256', $material);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizePayload($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeResponseBody(Response $response): ?array
    {
        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function shouldPersist(int $statusCode): bool
    {
        if ($statusCode >= 500) {
            return false;
        }

        return ($statusCode >= 200 && $statusCode < 300) || $statusCode === Response::HTTP_UNPROCESSABLE_ENTITY;
    }

    public function persistResponse(ApiIdempotencyKey $record, Response $response): void
    {
        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $this->decodeResponseBody($response),
        ]);
    }

    public function releaseClaim(ApiIdempotencyKey $record): void
    {
        $record->delete();
    }
}

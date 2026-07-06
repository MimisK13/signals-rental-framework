<?php

namespace App\Actions\Api;

use App\Support\Api\MeteringStatusClass;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExecuteExplorerRequest
{
    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     * @return array{
     *     status: int,
     *     status_class: string,
     *     headers: array<string, list<string>>,
     *     body: string,
     *     duration_ms: int,
     *     error?: string
     * }
     */
    public function __invoke(
        string $method,
        string $path,
        array $query,
        array $headers,
        string $bearerToken,
    ): array {
        $startedAt = microtime(true);
        $url = $this->buildUrl($path);
        $filteredQuery = $this->filterPairs($query);
        $filteredHeaders = $this->filterPairs($headers);
        unset($filteredHeaders['Authorization'], $filteredHeaders['authorization']);

        try {
            $response = Http::withToken($bearerToken)
                ->withHeaders($filteredHeaders)
                ->timeout(15)
                ->send(strtoupper($method), $url, [
                    'query' => $filteredQuery,
                ]);

            return [
                'status' => $response->status(),
                'status_class' => MeteringStatusClass::fromStatusCode($response->status()),
                'headers' => $this->maskSensitiveHeaders($response->headers()),
                'body' => $this->formatBody($response),
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
            ];
        } catch (ConnectionException $exception) {
            return [
                'status' => 0,
                'status_class' => '5xx',
                'headers' => [],
                'body' => '',
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => 'Connection failed: '.$exception->getMessage(),
            ];
        }
    }

    public static function maskToken(?string $token): string
    {
        if ($token === null || $token === '') {
            return '••••••••';
        }

        if (Str::length($token) <= 8) {
            return '••••••••';
        }

        return Str::substr($token, 0, 4).'••••'.Str::substr($token, -4);
    }

    private function buildUrl(string $path): string
    {
        $normalisedPath = Str::startsWith($path, '/') ? $path : '/'.$path;

        return rtrim((string) config('app.url'), '/').$normalisedPath;
    }

    /**
     * @param  array<string, string>  $pairs
     * @return array<string, string>
     */
    private function filterPairs(array $pairs): array
    {
        return array_filter(
            $pairs,
            static fn (mixed $value, string $key): bool => trim($key) !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, list<string>>
     */
    private function maskSensitiveHeaders(array $headers): array
    {
        $masked = [];

        foreach ($headers as $name => $values) {
            if (strtolower((string) $name) === 'authorization') {
                $masked[$name] = ['Bearer '.self::maskToken('')];

                continue;
            }

            $masked[$name] = array_values($values);
        }

        return $masked;
    }

    private function formatBody(Response $response): string
    {
        $body = $response->body();

        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return (string) json_encode(
                $decoded,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        return $body;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}

<?php

namespace App\Queries\Api;

use App\Enums\MeteringChannel;
use App\Models\UsageDaily;
use App\Support\DateWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class UsageQuery
{
    /**
     * @param  list<string>  $dimensions
     */
    private const VALID_DIMENSIONS = ['token', 'endpoint', 'resource', 'day', 'status'];

    public function __invoke(
        DateWindow $window,
        string $dimension = 'endpoint',
        ?MeteringChannel $channel = null,
        ?int $tokenId = null,
    ): UsageQueryResult {
        if (! in_array($dimension, self::VALID_DIMENSIONS, true)) {
            throw new \InvalidArgumentException("Unknown usage dimension: {$dimension}");
        }

        $baseQuery = $this->baseQuery($window, $channel, $tokenId);

        $rows = $this->aggregateRows($baseQuery, $dimension);
        $totals = $this->aggregateTotals($baseQuery);

        return new UsageQueryResult($rows, $totals);
    }

    /**
     * @return Builder<UsageDaily>
     */
    private function baseQuery(DateWindow $window, ?MeteringChannel $channel, ?int $tokenId): Builder
    {
        $query = UsageDaily::query()
            ->whereDate('date', '>=', $window->fromDate())
            ->whereDate('date', '<=', $window->toDate());

        if ($channel !== null) {
            $query->where('channel', $channel->value);
        }

        if ($tokenId !== null) {
            $query->where('personal_access_token_id', $tokenId);
        }

        return $query;
    }

    /**
     * @param  Builder<UsageDaily>  $baseQuery
     * @return list<array{key: string, label: string|null, request_count: int, weighted_units: int}>
     */
    private function aggregateRows(Builder $baseQuery, string $dimension): array
    {
        [$groupColumn, $keyAlias] = match ($dimension) {
            'token' => ['personal_access_token_id', 'group_key'],
            'endpoint' => ['key', 'group_key'],
            'resource' => ['resource', 'group_key'],
            'day' => ['date', 'group_key'],
            'status' => ['status_class', 'group_key'],
            default => throw new \InvalidArgumentException("Unknown usage dimension: {$dimension}"),
        };

        $aggregates = (clone $baseQuery)
            ->selectRaw("{$groupColumn} as {$keyAlias}")
            ->selectRaw('SUM(request_count) as request_count')
            ->selectRaw('SUM(weighted_units) as weighted_units')
            ->groupBy($groupColumn)
            ->orderByDesc('request_count')
            ->get();

        $tokenNames = $dimension === 'token'
            ? $this->tokenNamesForRows($aggregates->pluck('group_key')->all())
            : [];

        return $aggregates->map(function (UsageDaily $row) use ($dimension, $tokenNames): array {
            $key = $this->normalizeKey($row->getAttribute('group_key'));

            return [
                'key' => $key,
                'label' => $this->resolveLabel($dimension, $key, $tokenNames),
                'request_count' => (int) $row->request_count,
                'weighted_units' => (int) $row->weighted_units,
            ];
        })->values()->all();
    }

    /**
     * @param  Builder<UsageDaily>  $baseQuery
     * @return array{request_count: int, weighted_units: int}
     */
    private function aggregateTotals(Builder $baseQuery): array
    {
        $totals = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(request_count), 0) as request_count')
            ->selectRaw('COALESCE(SUM(weighted_units), 0) as weighted_units')
            ->first();

        return [
            'request_count' => (int) ($totals->request_count ?? 0),
            'weighted_units' => (int) ($totals->weighted_units ?? 0),
        ];
    }

    /**
     * @param  list<mixed>  $rawKeys
     * @return array<int, string>
     */
    private function tokenNamesForRows(array $rawKeys): array
    {
        $tokenIds = collect($rawKeys)
            ->filter(static fn (mixed $key): bool => $key !== null && $key !== '')
            ->map(static fn (mixed $key): int => (int) $key)
            ->unique()
            ->values()
            ->all();

        if ($tokenIds === []) {
            return [];
        }

        return PersonalAccessToken::query()
            ->whereIn('id', $tokenIds)
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (string $name, int $id): array => [$id => $name])
            ->all();
    }

    private function normalizeKey(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        return (string) $value;
    }

    /**
     * @param  array<int, string>  $tokenNames
     */
    private function resolveLabel(string $dimension, string $key, array $tokenNames): ?string
    {
        return match ($dimension) {
            'token' => $key === 'null' ? null : ($tokenNames[(int) $key] ?? null),
            'day' => $key,
            default => $key === 'null' ? null : $key,
        };
    }
}

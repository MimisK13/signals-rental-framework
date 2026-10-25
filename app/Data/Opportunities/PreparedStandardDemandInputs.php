<?php

namespace App\Data\Opportunities;

use App\Models\Demand;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use LogicException;

final readonly class PreparedStandardDemandInputs
{
    /**
     * @param  array<string, Collection<int, Demand>>  $demandsByKey
     */
    public function __construct(private array $demandsByKey) {}

    public static function key(
        int $productId,
        int $storeId,
        Carbon $from,
        Carbon $to,
        string $mode = 'active-overlap',
    ): string {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return implode('|', [
            $mode,
            $productId,
            $storeId,
            $from->copy()->utc()->format('Y-m-d\\TH:i:s.uP'),
            $to->copy()->utc()->format('Y-m-d\\TH:i:s.uP'),
        ]);
    }

    public function has(int $productId, int $storeId, Carbon $from, Carbon $to): bool
    {
        return array_key_exists(self::key($productId, $storeId, $from, $to), $this->demandsByKey);
    }

    /**
     * Return a fresh collection wrapper so consumer-specific filtering cannot
     * replace or reorder the canonical raw collection held by this input.
     *
     * @return Collection<int, Demand>
     */
    public function required(int $productId, int $storeId, Carbon $from, Carbon $to): Collection
    {
        $key = self::key($productId, $storeId, $from, $to);

        if (! array_key_exists($key, $this->demandsByKey)) {
            throw new LogicException("Prepared standard Demand {$key} is missing from the line-items read inputs.");
        }

        return new Collection($this->demandsByKey[$key]->all());
    }
}

<?php

namespace App\Services\Shortages;

/**
 * Deterministic largest-remainder apportionment for integer minor-unit totals.
 *
 * Remainder pennies are distributed to keys with the largest fractional parts;
 * ties break on ascending key order (stable, replay-safe).
 */
class MoneyApportionment
{
    /**
     * Split a minor-unit total across weighted keys.
     *
     * @param  array<int|string, int>  $weights  non-negative integer weights
     * @return array<int|string, int>
     */
    public function apportion(array $weights, int $targetSum): array
    {
        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $floors = [];
        $remainders = [];

        foreach ($weights as $key => $weight) {
            $exact = bcdiv(
                bcmul((string) $targetSum, (string) $weight, 10),
                (string) $totalWeight,
                10,
            );
            $floor = (int) bcadd($exact, '0', 0);
            $floors[$key] = $floor;
            $remainders[$key] = bcsub($exact, (string) $floor, 10);
        }

        $assigned = array_sum($floors);
        $remaining = $targetSum - $assigned;

        if ($remaining === 0) {
            return $floors;
        }

        $keys = array_keys($weights);
        usort($keys, function (int|string $left, int|string $right) use ($remainders): int {
            $cmp = bccomp($remainders[$right], $remainders[$left], 10);

            if ($cmp !== 0) {
                return $cmp;
            }

            return $left <=> $right;
        });

        $step = $remaining > 0 ? 1 : -1;
        $count = abs($remaining);

        for ($i = 0; $i < $count; $i++) {
            $key = $keys[$i % count($keys)];
            $floors[$key] += $step;
        }

        return $floors;
    }
}

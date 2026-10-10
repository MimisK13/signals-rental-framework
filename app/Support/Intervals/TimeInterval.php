<?php

namespace App\Support\Intervals;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class TimeInterval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($this->start->greaterThanOrEqualTo($this->end)) {
            throw new InvalidArgumentException('An interval must end after it starts.');
        }
    }

    public function clippedTo(CarbonImmutable $from, CarbonImmutable $until): ?self
    {
        $start = $this->start->greaterThan($from) ? $this->start : $from;
        $end = $this->end->lessThan($until) ? $this->end : $until;

        return $start->lessThan($end) ? new self($start, $end) : null;
    }

    public function intersection(self $other): ?self
    {
        return $this->clippedTo($other->start, $other->end);
    }

    public function seconds(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    /**
     * @param  iterable<self>  $intervals
     * @return list<self>
     */
    public static function union(iterable $intervals): array
    {
        $sorted = collect($intervals)
            ->sortBy(fn (self $interval): int => $interval->start->getTimestamp())
            ->values()
            ->all();

        $merged = [];

        foreach ($sorted as $interval) {
            $last = $merged[array_key_last($merged)] ?? null;

            if ($last === null || $interval->start->greaterThan($last->end)) {
                $merged[] = $interval;

                continue;
            }

            $merged[array_key_last($merged)] = new self(
                $last->start,
                $interval->end->greaterThan($last->end) ? $interval->end : $last->end,
            );
        }

        return $merged;
    }

    /** @param iterable<self> $intervals */
    public static function totalSeconds(iterable $intervals): int
    {
        return array_sum(array_map(static fn (self $interval): int => $interval->seconds(), self::union($intervals)));
    }

    /**
     * @param  iterable<self>  $subtrahends
     * @return list<self>
     */
    public function subtract(iterable $subtrahends): array
    {
        $remaining = [$this];

        foreach (self::union($subtrahends) as $subtrahend) {
            $next = [];

            foreach ($remaining as $interval) {
                if ($subtrahend->end->lessThanOrEqualTo($interval->start)
                    || $subtrahend->start->greaterThanOrEqualTo($interval->end)) {
                    $next[] = $interval;

                    continue;
                }

                if ($interval->start->lessThan($subtrahend->start)) {
                    $next[] = new self($interval->start, $subtrahend->start);
                }

                if ($subtrahend->end->lessThan($interval->end)) {
                    $next[] = new self($subtrahend->end, $interval->end);
                }
            }

            $remaining = $next;
        }

        return $remaining;
    }
}

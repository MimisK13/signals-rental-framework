<?php

namespace App\ValueObjects;

use Illuminate\Support\Carbon;

/**
 * A batch of shortages sharing the same product and store, used for consolidated
 * sub-hire resolution (shortage-resolution-sub-hires.md §3.5).
 *
 * @param  list<Shortage>  $shortages
 */
final readonly class ShortageGroup
{
    /**
     * @param  list<Shortage>  $shortages
     */
    public function __construct(
        public int $productId,
        public string $productName,
        public int $storeId,
        public Carbon $dateRangeStart,
        public Carbon $dateRangeEnd,
        public int $totalShortfall,
        public array $shortages,
    ) {}

    public function opportunityCount(): int
    {
        return count(array_unique(array_map(
            static fn (Shortage $shortage): int => $shortage->opportunityId,
            $this->shortages,
        )));
    }

    public function isCrossOpportunity(): bool
    {
        return $this->opportunityCount() >= 2;
    }

    /**
     * @return list<int>
     */
    public function opportunityItemIds(): array
    {
        return array_map(
            static fn (Shortage $shortage): int => $shortage->opportunityItemId,
            $this->shortages,
        );
    }
}

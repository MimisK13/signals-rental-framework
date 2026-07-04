<?php

namespace App\Services\Shortages;

use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use App\ValueObjects\ShortageGroup;

/**
 * Groups unresolved shortages by product + store for batch resolution.
 */
class ShortageBatchGrouper
{
    /**
     * @return list<ShortageGroup>
     */
    public function groupsFrom(ShortageCollection $shortages): array
    {
        /** @var array<string, list<Shortage>> $buckets */
        $buckets = [];

        foreach ($shortages->unresolved() as $shortage) {
            $key = $shortage->productId.':'.$shortage->storeId;
            $buckets[$key][] = $shortage;
        }

        $groups = [];

        foreach ($buckets as $groupShortages) {
            if (count($groupShortages) < 2) {
                continue;
            }

            $groups[] = $this->buildGroup($groupShortages);
        }

        return $groups;
    }

    /**
     * Batch groups that include at least one shortage from the given opportunity.
     *
     * @return list<ShortageGroup>
     */
    public function groupsForOpportunity(ShortageCollection $shortages, int $opportunityId): array
    {
        return array_values(array_filter(
            $this->groupsFrom($shortages),
            static fn (ShortageGroup $group): bool => in_array(
                $opportunityId,
                array_map(static fn (Shortage $shortage): int => $shortage->opportunityId, $group->shortages),
                true,
            ),
        ));
    }

    /**
     * @param  list<Shortage>  $shortages
     */
    private function buildGroup(array $shortages): ShortageGroup
    {
        $first = $shortages[0];

        $starts = $shortages[0]->startsAt->copy();
        $ends = $shortages[0]->endsAt->copy();

        $totalShortfall = 0;

        foreach ($shortages as $shortage) {
            if ($shortage->startsAt->lt($starts)) {
                $starts = $shortage->startsAt->copy();
            }

            if ($shortage->endsAt->gt($ends)) {
                $ends = $shortage->endsAt->copy();
            }

            $totalShortfall += $shortage->remainingShortfall();
        }

        return new ShortageGroup(
            productId: $first->productId,
            productName: $first->productName,
            storeId: $first->storeId,
            dateRangeStart: $starts,
            dateRangeEnd: $ends,
            totalShortfall: $totalShortfall,
            shortages: $shortages,
        );
    }
}

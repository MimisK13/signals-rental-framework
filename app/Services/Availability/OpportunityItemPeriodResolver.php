<?php

namespace App\Services\Availability;

use App\Models\Demand;
use App\Models\OpportunityItem;
use Illuminate\Support\Carbon;

/** Resolves explicit planning periods while preserving legacy Demand behavior. */
class OpportunityItemPeriodResolver
{
    /** @return array{0: Carbon, 1: Carbon} */
    public function planningPeriod(OpportunityItem $item): array
    {
        $opportunity = $item->opportunity;
        $hasExplicitPlanning = $item->planning_starts_at !== null
            || $item->planning_ends_at !== null
            || $opportunity->planning_starts_at !== null
            || $opportunity->planning_ends_at !== null;

        if ($hasExplicitPlanning) {
            $period = [
                Carbon::parse($item->planning_starts_at ?? $opportunity->planning_starts_at ?? $this->legacyStart($item)),
                Carbon::parse($item->planning_ends_at ?? $opportunity->planning_ends_at ?? $this->legacyEnd($item)),
            ];

            if ($period[0]->greaterThanOrEqualTo($period[1])) {
                throw new \InvalidArgumentException('The planning period must end after it starts.');
            }

            return $period;
        }

        if ((string) settings('availability.demand_date_source', 'operational') === 'charge') {
            return [
                Carbon::parse($opportunity->charge_starts_at ?? $this->legacyStart($item)),
                Carbon::parse($opportunity->charge_ends_at ?? $this->legacyEnd($item)),
            ];
        }

        return [$this->legacyStart($item), $this->legacyEnd($item)];
    }

    private function legacyStart(OpportunityItem $item): Carbon
    {
        return Carbon::parse($item->starts_at ?? $item->opportunity->starts_at ?? Carbon::now('UTC'));
    }

    private function legacyEnd(OpportunityItem $item): Carbon
    {
        return Carbon::parse($item->ends_at ?? $item->opportunity->ends_at ?? Demand::sentinel());
    }
}

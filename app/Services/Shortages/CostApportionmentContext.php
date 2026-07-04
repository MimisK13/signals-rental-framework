<?php

namespace App\Services\Shortages;

use App\Models\Opportunity;
use App\Models\VirtualStockIntake;
use Illuminate\Support\Carbon;

/**
 * Resolved participation and costing inputs for a virtual stock intake.
 */
class CostApportionmentContext
{
    /**
     * @param  array<int, int>  $quantitiesByOpportunity  opportunity_id => allocated quantity
     * @param  array<int, Opportunity>  $opportunities
     * @param  array<int, array{from: Carbon, until: Carbon}>  $rentalWindowsByOpportunity
     */
    public function __construct(
        public readonly VirtualStockIntake $intake,
        public readonly array $quantitiesByOpportunity,
        public readonly array $opportunities,
        public readonly ?int $primaryOpportunityId,
        public readonly int $totalCostMinor,
        public readonly array $rentalWindowsByOpportunity = [],
    ) {}

    /**
     * @return list<int>
     */
    public function participatingOpportunityIds(): array
    {
        return array_keys($this->quantitiesByOpportunity);
    }

    /**
     * @return array{from: Carbon, until: Carbon}|null
     */
    public function opportunityWindow(int $opportunityId): ?array
    {
        if (isset($this->rentalWindowsByOpportunity[$opportunityId])) {
            return $this->rentalWindowsByOpportunity[$opportunityId];
        }

        $opportunity = $this->opportunities[$opportunityId] ?? null;

        if ($opportunity === null) {
            return null;
        }

        $from = $opportunity->starts_at ?? $opportunity->charge_starts_at;
        $until = $opportunity->ends_at ?? $opportunity->charge_ends_at;

        if ($from === null || $until === null) {
            return null;
        }

        return [
            'from' => Carbon::parse($from)->utc(),
            'until' => Carbon::parse($until)->utc(),
        ];
    }
}

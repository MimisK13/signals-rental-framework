<?php

namespace App\Services\Shortages\Strategies;

use App\Services\Shortages\CostApportionmentContext;
use App\Services\Shortages\IntakeOverlapDays;
use App\Services\Shortages\MoneyApportionment;

/**
 * Splits cost by quantity × overlap-days (unit-days) per opportunity.
 *
 * Zero-overlap opportunities remain listed with zero allocation. Overlap uses
 * half-open [available_from, available_until) intake windows against opportunity
 * starts_at/ends_at (falling back to charge_starts_at/charge_ends_at).
 */
class ProportionalQuantityDurationCostApportionmentStrategy extends AbstractCostApportionmentStrategy
{
    public function __construct(
        MoneyApportionment $apportionment,
        private readonly IntakeOverlapDays $overlapDays,
    ) {
        parent::__construct($apportionment);
    }

    public function key(): string
    {
        return 'proportional_quantity_duration';
    }

    public function name(): string
    {
        return 'Proportional by quantity × duration';
    }

    /**
     * @return array<int, int>
     */
    protected function calculateForContext(CostApportionmentContext $context): array
    {
        $intake = $context->intake;
        $weights = [];

        foreach ($context->quantitiesByOpportunity as $opportunityId => $quantity) {
            $window = $context->opportunityWindow($opportunityId);

            if ($window === null) {
                $weights[$opportunityId] = 0;

                continue;
            }

            $days = $this->overlapDays->count(
                $intake->available_from,
                $intake->available_until,
                $window['from'],
                $window['until'],
            );

            $weights[$opportunityId] = $quantity * $days;
        }

        if ($weights === [] || array_sum($weights) === 0) {
            return array_fill_keys(array_keys($context->quantitiesByOpportunity), 0);
        }

        return $this->apportion($context, $weights);
    }
}

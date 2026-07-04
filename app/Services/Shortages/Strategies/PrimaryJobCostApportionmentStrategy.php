<?php

namespace App\Services\Shortages\Strategies;

use App\Services\Shortages\CostApportionmentContext;

/**
 * Assigns the full intake cost to the primary opportunity.
 *
 * Primary = persisted intake `primary_opportunity_id`, else resolution metadata
 * `opportunity_id`, else the first participating opportunity (lowest id after
 * deterministic sort).
 */
class PrimaryJobCostApportionmentStrategy extends AbstractCostApportionmentStrategy
{
    public function key(): string
    {
        return 'primary_job';
    }

    public function name(): string
    {
        return 'Primary job';
    }

    /**
     * @return array<int, int>
     */
    protected function calculateForContext(CostApportionmentContext $context): array
    {
        $participants = $context->participatingOpportunityIds();

        if ($participants === []) {
            return [];
        }

        $primaryId = $context->primaryOpportunityId ?? $participants[0];
        $allocations = array_fill_keys($participants, 0);
        $allocations[$primaryId] = $context->totalCostMinor;

        return $allocations;
    }
}

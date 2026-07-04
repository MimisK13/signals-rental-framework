<?php

namespace App\Services\Shortages\Strategies;

use App\Services\Shortages\CostApportionmentContext;

/**
 * Splits cost by each opportunity's allocated quantity from the resolution pivot.
 */
class ProportionalQuantityCostApportionmentStrategy extends AbstractCostApportionmentStrategy
{
    public function key(): string
    {
        return 'proportional_quantity';
    }

    public function name(): string
    {
        return 'Proportional by quantity';
    }

    /**
     * @return array<int, int>
     */
    protected function calculateForContext(CostApportionmentContext $context): array
    {
        $weights = $context->quantitiesByOpportunity;

        if ($weights === []) {
            return [];
        }

        return $this->apportion($context, $weights);
    }
}

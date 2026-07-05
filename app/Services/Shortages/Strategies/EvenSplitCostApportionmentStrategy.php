<?php

namespace App\Services\Shortages\Strategies;

use App\Services\Shortages\CostApportionmentContext;

/**
 * Splits cost equally across participating opportunities.
 *
 * Remainder pennies go to the lowest opportunity id (deterministic first key).
 */
class EvenSplitCostApportionmentStrategy extends AbstractCostApportionmentStrategy
{
    public function key(): string
    {
        return 'even_split';
    }

    public function name(): string
    {
        return 'Even split';
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

        $weights = array_fill_keys($participants, 1);

        return $this->apportion($context, $weights);
    }
}

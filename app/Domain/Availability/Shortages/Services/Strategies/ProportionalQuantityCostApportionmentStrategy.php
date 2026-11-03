<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Services\Strategies;

use App\Domain\Availability\Shortages\Services\CostApportionmentContext;

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

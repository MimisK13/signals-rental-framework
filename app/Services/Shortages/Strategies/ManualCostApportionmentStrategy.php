<?php

namespace App\Services\Shortages\Strategies;

use App\Models\VirtualStockCostOverride;
use App\Services\Shortages\CostApportionmentContext;
use InvalidArgumentException;

/**
 * Uses per-opportunity unit cost overrides × pivot quantity.
 *
 * Missing overrides allocate zero. Sum mismatch vs intake total logs a warning
 * via the action result flag — it does not block apportionment.
 */
class ManualCostApportionmentStrategy extends AbstractCostApportionmentStrategy
{
    public function key(): string
    {
        return 'manual';
    }

    public function name(): string
    {
        return 'Manual';
    }

    public function requiresManualInput(): bool
    {
        return true;
    }

    /**
     * @return array<int, int>
     */
    protected function calculateForContext(CostApportionmentContext $context): array
    {
        $overrides = VirtualStockCostOverride::query()
            ->where('virtual_stock_intake_id', $context->intake->id)
            ->get()
            ->keyBy('opportunity_id');

        $allocations = [];

        foreach ($context->quantitiesByOpportunity as $opportunityId => $quantity) {
            $override = $overrides->get($opportunityId);
            $allocations[$opportunityId] = $override !== null
                ? (int) $override->unit_cost_override * $quantity
                : 0;
        }

        return $allocations;
    }

    public function calculate(mixed $intake): array
    {
        if (! $intake instanceof CostApportionmentContext) {
            throw new InvalidArgumentException('Expected CostApportionmentContext.');
        }

        return $this->calculateForContext($intake);
    }
}

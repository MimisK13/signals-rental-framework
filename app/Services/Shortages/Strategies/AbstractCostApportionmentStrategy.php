<?php

namespace App\Services\Shortages\Strategies;

use App\Contracts\CostApportionmentStrategyContract;
use App\Services\Shortages\CostApportionmentContext;
use App\Services\Shortages\MoneyApportionment;
use InvalidArgumentException;

abstract class AbstractCostApportionmentStrategy implements CostApportionmentStrategyContract
{
    public function __construct(
        protected readonly MoneyApportionment $apportionment,
    ) {}

    public function requiresManualInput(): bool
    {
        return false;
    }

    /**
     * @return array<int, int>
     */
    public function calculate(mixed $intake): array
    {
        if (! $intake instanceof CostApportionmentContext) {
            throw new InvalidArgumentException('Expected CostApportionmentContext.');
        }

        return $this->calculateForContext($intake);
    }

    /**
     * @return array<int, int>
     */
    abstract protected function calculateForContext(CostApportionmentContext $context): array;

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    protected function apportion(CostApportionmentContext $context, array $weights): array
    {
        $keys = array_keys($weights);
        sort($keys, SORT_NUMERIC);

        $sorted = [];
        foreach ($keys as $key) {
            $sorted[$key] = max(0, $weights[$key]);
        }

        return $this->apportionment->apportion($sorted, $context->totalCostMinor);
    }
}

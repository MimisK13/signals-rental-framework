<?php

namespace App\Data\VirtualStock;

use Spatie\LaravelData\Data;

class ApportionVirtualStockCostResult extends Data
{
    /**
     * @param  array<int, int>  $allocations  opportunity_id => minor units
     */
    public function __construct(
        public int $virtual_stock_intake_id,
        public string $strategy_key,
        public array $allocations,
        public bool $manual_mismatch_warning = false,
        public int $intake_total_minor = 0,
        public int $allocated_total_minor = 0,
    ) {}
}

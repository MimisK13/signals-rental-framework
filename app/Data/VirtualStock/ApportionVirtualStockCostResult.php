<?php

namespace App\Data\VirtualStock;

use App\Data\Concerns\StripsCostFields;
use Spatie\LaravelData\Data;

class ApportionVirtualStockCostResult extends Data
{
    use StripsCostFields;

    /** @return list<string> */
    protected static function costFieldKeys(): array
    {
        return ['allocations', 'intake_total_minor', 'allocated_total_minor'];
    }

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

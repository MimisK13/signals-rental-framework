<?php

namespace App\Data\VirtualStock;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SetVirtualStockCostOverrideData extends Data
{
    public function __construct(
        #[Required, Exists('opportunities', 'id')]
        public int $opportunity_id,
        #[Required, Min(0)]
        public int $unit_cost_override,
    ) {}
}

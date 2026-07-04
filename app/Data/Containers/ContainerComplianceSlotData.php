<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

class ContainerComplianceSlotData extends Data
{
    public function __construct(
        public int $product_id,
        public int $expected_quantity,
        public int $actual_quantity,
        /** @var 'ok'|'under'|'over'|'unexpected' */
        public string $status,
    ) {}
}

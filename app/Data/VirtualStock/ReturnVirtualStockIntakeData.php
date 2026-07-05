<?php

namespace App\Data\VirtualStock;

use Spatie\LaravelData\Data;

class ReturnVirtualStockIntakeData extends Data
{
    public function __construct(
        public bool $force = false,
    ) {}
}

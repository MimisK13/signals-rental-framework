<?php

declare(strict_types=1);

namespace App\Domain\VirtualStock\Data;

use Spatie\LaravelData\Data;

class ReturnVirtualStockIntakeData extends Data
{
    public function __construct(
        public bool $force = false,
    ) {}
}

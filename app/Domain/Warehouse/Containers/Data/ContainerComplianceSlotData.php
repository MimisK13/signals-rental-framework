<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use Spatie\LaravelData\Data;

final class ContainerComplianceSlotData extends Data
{
    public function __construct(
        public int $product_id,
        public int $expected_quantity,
        public int $actual_quantity,
        /** @var 'ok'|'under'|'over'|'unexpected' */
        public string $status,
    ) {}
}

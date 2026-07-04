<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Per-product validation row for transport container dispatch.
 */
class ContainerDispatchProductVerdictData extends Data
{
    public function __construct(
        public int $product_id,
        public string $product_name,
        public string $verdict,
        public int $container_quantity,
        public string $opportunity_quantity,
        public int $allocated_quantity,
        public int $unallocated_quantity,
        public ?string $reason = null,
        public ?string $required_action = null,
        public ?string $suggested_quantity = null,
        public ?int $suggested_price = null,
    ) {}
}

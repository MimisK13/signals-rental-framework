<?php

namespace App\Data\Containers;

use Spatie\LaravelData\Data;

/**
 * Template compliance report for a container (serialised-containers.md §API →
 * GET /containers/{uuid}/compliance).
 */
class ContainerComplianceData extends Data
{
    /**
     * @param  list<ContainerComplianceSlotData>  $slots
     * @param  array<int, int>  $manifest  product_id => rolled-up quantity
     */
    public function __construct(
        public int $container_id,
        public string $container_uuid,
        public string $status,
        public bool $is_sealed,
        public string $scan_mode,
        public bool $compliant,
        public int $max_nesting_depth,
        public array $slots,
        public array $manifest,
    ) {}
}

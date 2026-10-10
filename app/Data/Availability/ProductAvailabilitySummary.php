<?php

namespace App\Data\Availability;

use Spatie\LaravelData\Data;

/**
 * Read model for one Product, Store and period, composed from the canonical
 * AvailabilityService read models.
 */
class ProductAvailabilitySummary extends Data
{
    /**
     * @param  list<AvailabilitySlotData>  $slots
     * @param  list<ProductAvailabilityDemandEntry>  $demand_entries
     */
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $from,
        public string $until,
        public int $physical_stock,
        public ?int $peak_demand,
        public ?int $minimum_availability,
        public ?int $peak_shortage,
        public ?bool $has_shortage,
        public ?string $calculated_at,
        public array $slots,
        public array $demand_entries,
    ) {}
}

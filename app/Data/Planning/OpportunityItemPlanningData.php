<?php

namespace App\Data\Planning;

use Spatie\LaravelData\Data;

/**
 * Read-only planning state for one product-backed opportunity line.
 *
 * Quantities that originate from the decimal opportunity line remain decimal
 * strings. Availability, shortage, external supply, and serialized allocation
 * are live integer read values supplied by their existing domain readers.
 */
final class OpportunityItemPlanningData extends Data
{
    public function __construct(
        public int $opportunity_item_id,
        public string $path,
        public ?string $parent_path,
        public int $depth,
        public int $product_id,
        public string $product_name,
        public int $store_id,
        public string $store_name,
        public string $planning_starts_at,
        public string $planning_ends_at,
        public string $requested_quantity,
        public ?string $reserved_quantity,
        public string $reservation_mode,
        public string $reservation_state,
        public int $effective_claim_quantity,
        public string $reservation_gap,
        public int $available_quantity,
        public int $owned_available_quantity,
        public int $external_supply_quantity,
        public int $shortage_quantity,
        public bool $has_shortage,
        public ?int $allocated_quantity,
        public ?string $allocation_state,
    ) {}
}

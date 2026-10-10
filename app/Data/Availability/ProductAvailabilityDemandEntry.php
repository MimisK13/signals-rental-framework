<?php

namespace App\Data\Availability;

use Spatie\LaravelData\Data;

/**
 * A presentation-ready, source-grouped demand entry for a Product availability
 * period. The quantity is the sum of canonical demand rows for one source.
 */
class ProductAvailabilityDemandEntry extends Data
{
    public function __construct(
        public string $source_type,
        public int $source_id,
        public string $label,
        public int $quantity,
        public string $from,
        public string $until,
        public string $phase,
    ) {}
}

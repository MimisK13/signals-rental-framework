<?php

namespace App\Data\Tax;

use Spatie\LaravelData\Data;

/**
 * One document line participating in grouped tax breakdown.
 */
class DocumentTaxLineInput extends Data
{
    public function __construct(
        public string $lineKey,
        public int $netMinor,
        public ?int $productTaxClassId = null,
        /** Decimal quantity string (same precision as opportunity_items.quantity). */
        public string $quantity = '1',
        public ?string $taxTreatment = null,
    ) {}
}

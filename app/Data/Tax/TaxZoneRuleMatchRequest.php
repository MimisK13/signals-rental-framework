<?php

namespace App\Data\Tax;

use App\Enums\TaxBuyerType;
use App\Models\TaxZone;
use Spatie\LaravelData\Data;

/**
 * Context for zone-based tax rule resolution.
 */
class TaxZoneRuleMatchRequest extends Data
{
    public function __construct(
        public int $seller_zone_id,
        public int $buyer_zone_id,
        public TaxBuyerType $buyer_type,
        public bool $has_tax_id = false,
        public ?int $tax_category_id = null,
    ) {}

    public static function fromZones(
        TaxZone $sellerZone,
        TaxZone $buyerZone,
        TaxBuyerType $buyerType,
        bool $hasTaxId = false,
        ?int $taxCategoryId = null,
    ): self {
        return new self(
            seller_zone_id: $sellerZone->id,
            buyer_zone_id: $buyerZone->id,
            buyer_type: $buyerType,
            has_tax_id: $hasTaxId,
            tax_category_id: $taxCategoryId,
        );
    }
}

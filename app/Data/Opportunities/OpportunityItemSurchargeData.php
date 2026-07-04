<?php

namespace App\Data\Opportunities;

use App\Models\OpportunityItemSurcharge;
use Spatie\LaravelData\Data;

/**
 * Serialised per-line surcharge row for API and UI consumers.
 *
 * Name and type resolve from snapshot columns first, falling back to the live
 * surcharge relation only for legacy rows missing snapshots.
 */
class OpportunityItemSurchargeData extends Data
{
    public function __construct(
        public int $id,
        public int $opportunity_item_id,
        public ?int $surcharge_id,
        public string $name,
        public string $surcharge_type,
        public ?string $percent_value,
        public ?int $fixed_value_minor,
        public string $amount,
        public ?int $product_tax_class_id,
        public bool $is_manual_override,
        public bool $is_disabled,
    ) {}

    public static function fromModel(OpportunityItemSurcharge $row): self
    {
        return new self(
            id: $row->id,
            opportunity_item_id: (int) $row->opportunity_item_id,
            surcharge_id: $row->surcharge_id !== null ? (int) $row->surcharge_id : null,
            name: $row->resolvedName(),
            surcharge_type: $row->resolvedSurchargeType(),
            percent_value: $row->resolvedPercentValue(),
            fixed_value_minor: $row->resolvedFixedValueMinor(),
            amount: $row->formatMoneyCost('amount'),
            product_tax_class_id: $row->product_tax_class_id !== null ? (int) $row->product_tax_class_id : null,
            is_manual_override: (bool) $row->is_manual_override,
            is_disabled: (bool) $row->is_disabled,
        );
    }
}

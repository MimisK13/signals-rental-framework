<?php

namespace App\Data\Tax;

use App\Models\TaxRule;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class TaxRuleData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?int $organisation_tax_class_id,
        public ?int $product_tax_class_id,
        public ?int $tax_rate_id,
        public ?int $seller_zone_id,
        public ?int $buyer_zone_id,
        public ?string $buyer_type,
        public ?bool $has_tax_id,
        public ?int $tax_category_id,
        public ?string $result_type,
        public ?string $legal_text,
        public int $priority,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(TaxRule $taxRule): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $taxRule->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $taxRule->updated_at;

        return new self(
            id: $taxRule->id,
            name: $taxRule->name,
            organisation_tax_class_id: $taxRule->organisation_tax_class_id,
            product_tax_class_id: $taxRule->product_tax_class_id,
            tax_rate_id: $taxRule->tax_rate_id,
            seller_zone_id: $taxRule->seller_zone_id,
            buyer_zone_id: $taxRule->buyer_zone_id,
            buyer_type: $taxRule->buyer_type?->value,
            has_tax_id: $taxRule->has_tax_id,
            tax_category_id: $taxRule->tax_category_id,
            result_type: $taxRule->result_type?->value,
            legal_text: $taxRule->legal_text,
            priority: $taxRule->priority,
            is_active: $taxRule->is_active,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

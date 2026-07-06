<?php

namespace App\Data\Pricing;

use App\Models\Surcharge;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class SurchargeData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        public int $id,
        public int $surcharge_group_id,
        public string $name,
        public ?string $percent_value,
        public ?int $fixed_value_minor,
        public ?array $criteria,
        public bool $is_default,
        public ?int $product_tax_class_id,
        public ?string $product_tax_class_name,
        public ?string $valid_from,
        public ?string $valid_to,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Surcharge $surcharge): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $surcharge->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $surcharge->updated_at;

        return new self(
            id: $surcharge->id,
            surcharge_group_id: $surcharge->surcharge_group_id,
            name: $surcharge->name,
            percent_value: $surcharge->percent_value !== null ? (string) $surcharge->percent_value : null,
            fixed_value_minor: $surcharge->fixed_value_minor,
            criteria: $surcharge->criteria,
            is_default: $surcharge->is_default,
            product_tax_class_id: $surcharge->product_tax_class_id,
            product_tax_class_name: $surcharge->relationLoaded('productTaxClass')
                ? $surcharge->productTaxClass?->name
                : null,
            valid_from: $surcharge->valid_from?->toIso8601String(),
            valid_to: $surcharge->valid_to?->toIso8601String(),
            sort_order: $surcharge->sort_order,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

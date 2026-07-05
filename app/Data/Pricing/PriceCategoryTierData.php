<?php

namespace App\Data\Pricing;

use App\Models\PriceCategoryTier;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class PriceCategoryTierData extends Data
{
    public function __construct(
        public int $id,
        public int $price_category_id,
        public int $tier_number,
        public ?string $label,
        public string $multiplier,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(PriceCategoryTier $tier): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $tier->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $tier->updated_at;

        return new self(
            id: $tier->id,
            price_category_id: $tier->price_category_id,
            tier_number: $tier->tier_number,
            label: $tier->label,
            multiplier: (string) $tier->multiplier,
            sort_order: $tier->sort_order,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

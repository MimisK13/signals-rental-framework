<?php

namespace App\Data\Pricing;

use App\Models\PriceCategory;
use App\Models\PriceCategoryTier;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class PriceCategoryData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $tiers
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_active,
        public array $tiers,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(PriceCategory $category): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $category->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $category->updated_at;

        $tiers = $category->relationLoaded('tiers')
            ? $category->tiers->map(fn (PriceCategoryTier $tier): array => PriceCategoryTierData::fromModel($tier)->toArray())->all()
            : [];

        return new self(
            id: $category->id,
            name: $category->name,
            description: $category->description,
            is_active: $category->is_active,
            tiers: $tiers,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

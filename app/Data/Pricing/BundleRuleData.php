<?php

namespace App\Data\Pricing;

use App\Models\BundleRule;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class BundleRuleData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $bundle_percent,
        public ?int $bundle_fixed_price_minor,
        public ?array $criteria,
        public bool $is_active,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(BundleRule $rule): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $rule->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $rule->updated_at;

        return new self(
            id: $rule->id,
            name: $rule->name,
            description: $rule->description,
            bundle_percent: $rule->bundle_percent !== null ? (string) $rule->bundle_percent : null,
            bundle_fixed_price_minor: $rule->bundle_fixed_price_minor,
            criteria: $rule->criteria,
            is_active: $rule->is_active,
            sort_order: $rule->sort_order,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

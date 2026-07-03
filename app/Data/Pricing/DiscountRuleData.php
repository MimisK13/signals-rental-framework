<?php

namespace App\Data\Pricing;

use App\Enums\DiscountRuleScope;
use App\Models\DiscountRule;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class DiscountRuleData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        public int $id,
        public int $discount_category_id,
        public string $discount_percent,
        public ?array $criteria,
        public string $rule_scope,
        public ?string $valid_from,
        public ?string $valid_to,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(DiscountRule $rule): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $rule->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $rule->updated_at;

        return new self(
            id: $rule->id,
            discount_category_id: $rule->discount_category_id,
            discount_percent: (string) $rule->discount_percent,
            criteria: $rule->criteria,
            rule_scope: ($rule->rule_scope ?? DiscountRuleScope::PerLine)->value,
            valid_from: $rule->valid_from?->toIso8601String(),
            valid_to: $rule->valid_to?->toIso8601String(),
            sort_order: $rule->sort_order,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}

<?php

namespace App\Actions\Pricing;

use App\Models\DiscountCategory;

class SyncDiscountCategoryRules
{
    /**
     * @param  list<array{
     *     discount_percent: string|float|int,
     *     criteria?: array<string, mixed>|null,
     *     rule_scope?: string|null,
     *     valid_from?: string|null,
     *     valid_to?: string|null,
     *     sort_order?: int
     * }>  $rules
     */
    public function __invoke(DiscountCategory $category, array $rules): void
    {
        $category->rules()->delete();

        foreach ($rules as $index => $rule) {
            $category->rules()->create([
                'discount_percent' => $rule['discount_percent'],
                'criteria' => $rule['criteria'] ?? [],
                'rule_scope' => $rule['rule_scope'] ?? 'per_line',
                'valid_from' => $rule['valid_from'] ?? null,
                'valid_to' => $rule['valid_to'] ?? null,
                'sort_order' => $rule['sort_order'] ?? $index,
            ]);
        }
    }
}

<?php

namespace App\Data\Pricing;

use App\Enums\DiscountRuleScope;
use App\Enums\RateTransactionType;
use Illuminate\Validation\Rule;

class DiscountRuleCriteriaValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function nestedRules(string $prefix = 'rules'): array
    {
        return [
            $prefix => ['sometimes', 'nullable', 'array'],
            "{$prefix}.*.discount_percent" => ['required', 'numeric', 'min:0', 'max:100'],
            "{$prefix}.*.rule_scope" => ['sometimes', 'string', Rule::in(array_column(DiscountRuleScope::cases(), 'value'))],
            "{$prefix}.*.criteria" => ['sometimes', 'nullable', 'array'],
            "{$prefix}.*.criteria.product_group_ids" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.product_group_ids.*" => ['integer', Rule::exists('product_groups', 'id')],
            "{$prefix}.*.criteria.product_ids" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.product_ids.*" => ['integer', Rule::exists('products', 'id')],
            "{$prefix}.*.criteria.tag_names" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.tag_names.*" => ['string', 'max:255'],
            "{$prefix}.*.criteria.transaction_types" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.transaction_types.*" => ['string', Rule::in(array_column(RateTransactionType::cases(), 'value'))],
            "{$prefix}.*.criteria.quantity_min" => ['sometimes', 'nullable', 'integer', 'min:0'],
            "{$prefix}.*.criteria.quantity_max" => ['sometimes', 'nullable', 'integer', 'min:0'],
            "{$prefix}.*.valid_from" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.valid_to" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.sort_order" => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

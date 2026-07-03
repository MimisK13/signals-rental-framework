<?php

namespace App\Data\Pricing;

use App\Enums\RateTransactionType;
use Illuminate\Validation\Rule;

class SurchargeCriteriaValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function nestedRules(string $prefix = 'surcharges'): array
    {
        return array_merge([
            $prefix => ['sometimes', 'nullable', 'array'],
            "{$prefix}.*.name" => ['required', 'string', 'max:255'],
            "{$prefix}.*.percent_value" => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            "{$prefix}.*.fixed_value_minor" => ['sometimes', 'nullable', 'integer', 'min:0'],
            "{$prefix}.*.is_default" => ['sometimes', 'boolean'],
            "{$prefix}.*.product_tax_class_id" => ['sometimes', 'nullable', 'integer', Rule::exists('product_tax_classes', 'id')],
            "{$prefix}.*.criteria" => ['sometimes', 'nullable', 'array'],
            "{$prefix}.*.criteria.product_group_ids" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.product_group_ids.*" => ['integer', Rule::exists('product_groups', 'id')],
            "{$prefix}.*.criteria.product_ids" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.product_ids.*" => ['integer', Rule::exists('products', 'id')],
            "{$prefix}.*.criteria.tag_names" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.tag_names.*" => ['string', 'max:255'],
            "{$prefix}.*.criteria.transaction_types" => ['sometimes', 'array'],
            "{$prefix}.*.criteria.transaction_types.*" => ['string', Rule::in(array_column(RateTransactionType::cases(), 'value'))],
            "{$prefix}.*.valid_from" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.valid_to" => ['sometimes', 'nullable', 'date'],
            "{$prefix}.*.sort_order" => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}

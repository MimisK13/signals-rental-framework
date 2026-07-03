<?php

namespace App\Data\Pricing;

use App\Enums\DiscountRuleScope;
use App\Enums\RateTransactionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateDiscountRuleData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        #[Required]
        public int $discount_category_id,
        #[Required, Max(100)]
        public string $discount_percent,
        public ?array $criteria = null,
        public string $rule_scope = 'per_line',
        public ?string $valid_from = null,
        public ?string $valid_to = null,
        public int $sort_order = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'discount_category_id' => ['required', 'integer', Rule::exists('discount_categories', 'id')],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria' => ['sometimes', 'nullable', 'array'],
            'criteria.product_group_ids' => ['sometimes', 'array'],
            'criteria.product_group_ids.*' => ['integer', Rule::exists('product_groups', 'id')],
            'criteria.product_ids' => ['sometimes', 'array'],
            'criteria.product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'criteria.tag_names' => ['sometimes', 'array'],
            'criteria.tag_names.*' => ['string', 'max:255'],
            'criteria.transaction_types' => ['sometimes', 'array'],
            'criteria.transaction_types.*' => ['string', Rule::in(array_column(RateTransactionType::cases(), 'value'))],
            'criteria.quantity_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'criteria.quantity_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'rule_scope' => ['sometimes', 'string', Rule::in(array_column(DiscountRuleScope::cases(), 'value'))],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

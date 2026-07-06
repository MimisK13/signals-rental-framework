<?php

namespace App\Data\Pricing;

use App\Enums\RateTransactionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateBundleRuleData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $bundle_percent = null,
        public ?int $bundle_fixed_price_minor = null,
        public ?array $criteria = null,
        public ?bool $is_active = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'bundle_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'bundle_fixed_price_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'criteria' => ['sometimes', 'nullable', 'array'],
            'criteria.product_group_ids' => ['sometimes', 'array'],
            'criteria.product_group_ids.*' => ['integer', Rule::exists('product_groups', 'id')],
            'criteria.product_ids' => ['sometimes', 'array'],
            'criteria.product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'criteria.tag_names' => ['sometimes', 'array'],
            'criteria.tag_names.*' => ['string', 'max:255'],
            'criteria.transaction_types' => ['sometimes', 'array'],
            'criteria.transaction_types.*' => ['string', Rule::in(array_column(RateTransactionType::cases(), 'value'))],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

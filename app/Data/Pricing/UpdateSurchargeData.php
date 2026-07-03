<?php

namespace App\Data\Pricing;

use App\Enums\RateTransactionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

class UpdateSurchargeData extends Data
{
    /**
     * @param  array<string, mixed>|null  $criteria
     */
    public function __construct(
        #[Max(255)]
        public ?string $name = null,
        public ?string $percent_value = null,
        public ?int $fixed_value_minor = null,
        public ?array $criteria = null,
        public ?bool $is_default = null,
        public ?int $product_tax_class_id = null,
        public ?string $valid_from = null,
        public ?string $valid_to = null,
        public ?int $sort_order = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'percent_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_value_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'criteria' => ['sometimes', 'nullable', 'array'],
            'criteria.product_group_ids' => ['sometimes', 'array'],
            'criteria.product_group_ids.*' => ['integer', Rule::exists('product_groups', 'id')],
            'criteria.product_ids' => ['sometimes', 'array'],
            'criteria.product_ids.*' => ['integer', Rule::exists('products', 'id')],
            'criteria.tag_names' => ['sometimes', 'array'],
            'criteria.tag_names.*' => ['string', 'max:255'],
            'criteria.transaction_types' => ['sometimes', 'array'],
            'criteria.transaction_types.*' => ['string', Rule::in(array_column(RateTransactionType::cases(), 'value'))],
            'is_default' => ['sometimes', 'boolean'],
            'product_tax_class_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_tax_classes', 'id')],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

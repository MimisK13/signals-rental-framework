<?php

namespace App\Data\Opportunities;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class OverrideOpportunityItemSurchargeData extends Data
{
    public function __construct(
        #[Required]
        public int $surcharge_id,
        public ?int $amount_minor = null,
        public bool $is_disabled = false,
        public ?int $product_tax_class_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'surcharge_id' => ['required', 'integer', Rule::exists('surcharges', 'id')],
            'amount_minor' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_disabled' => ['sometimes', 'boolean'],
            'product_tax_class_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_tax_classes', 'id')],
        ];
    }
}

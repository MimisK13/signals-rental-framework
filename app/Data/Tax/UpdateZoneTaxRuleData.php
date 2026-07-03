<?php

namespace App\Data\Tax;

use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Sometimes;
use Spatie\LaravelData\Data;

class UpdateZoneTaxRuleData extends Data
{
    public function __construct(
        #[Sometimes, Max(255)]
        public ?string $name = null,
        public ?string $description = null,
        public ?int $seller_zone_id = null,
        public ?int $buyer_zone_id = null,
        public ?TaxBuyerType $buyer_type = null,
        public ?bool $has_tax_id = null,
        public ?int $tax_category_id = null,
        public ?TaxResultType $result_type = null,
        public ?string $legal_text = null,
        public ?int $priority = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'seller_zone_id' => ['sometimes', 'integer', 'exists:tax_zones,id'],
            'buyer_zone_id' => ['sometimes', 'integer', 'exists:tax_zones,id'],
            'buyer_type' => ['nullable', 'string', 'in:b2b,b2c'],
            'has_tax_id' => ['nullable', 'boolean'],
            'tax_category_id' => ['nullable', 'integer', 'exists:tax_categories,id'],
            'result_type' => ['sometimes', 'string', 'in:standard,reverse_charge,zero_rated,exempt,outside_scope'],
            'legal_text' => ['nullable', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

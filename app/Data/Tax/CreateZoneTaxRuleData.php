<?php

namespace App\Data\Tax;

use App\Data\Casts\TaxBuyerTypeCast;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class CreateZoneTaxRuleData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Exists('tax_zones', 'id')]
        public int $seller_zone_id,
        #[Required, Exists('tax_zones', 'id')]
        public int $buyer_zone_id,
        #[Required]
        public TaxResultType $result_type,
        public ?string $description = null,
        #[WithCast(TaxBuyerTypeCast::class)]
        public ?TaxBuyerType $buyer_type = null,
        public ?bool $has_tax_id = null,
        public ?int $tax_category_id = null,
        public ?string $legal_text = null,
        public int $priority = 0,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'seller_zone_id' => ['required', 'integer', 'exists:tax_zones,id'],
            'buyer_zone_id' => ['required', 'integer', 'exists:tax_zones,id'],
            'buyer_type' => ['nullable', 'string', 'in:'.implode(',', TaxBuyerType::validationValues())],
            'has_tax_id' => ['nullable', 'boolean'],
            'tax_category_id' => ['nullable', 'integer', 'exists:tax_categories,id'],
            'result_type' => ['required', 'string', 'in:standard,reverse_charge,zero_rated,exempt,outside_scope'],
            'legal_text' => ['nullable', 'string'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

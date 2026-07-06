<?php

namespace App\Data\SupplierCosts;

use App\Data\Casts\MoneyInput;
use App\Enums\MembershipType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class UpdateSupplierCostData extends Data
{
    public function __construct(
        public ?int $supplier_id = null,
        public ?string $cost_basis = null,
        #[WithCast(MoneyInput::class)]
        public ?int $cost = null,
        public ?string $currency_code = null,
        public ?int $store_id = null,
        public ?string $supplier_sku = null,
        public ?int $lead_time_days = null,
        public ?string $min_order_quantity = null,
        public ?bool $is_preferred = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
        public bool $store_id_provided = false,
        public bool $supplier_sku_provided = false,
        public bool $lead_time_days_provided = false,
        public bool $metadata_provided = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $costBases = array_keys(config('supplier_costs.cost_bases', []));

        return [
            'supplier_id' => [
                'sometimes',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'cost_basis' => ['sometimes', 'string', Rule::in($costBases)],
            'supplier_sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'min_order_quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'is_preferred' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

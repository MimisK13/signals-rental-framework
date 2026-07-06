<?php

namespace App\Data\SupplierCosts;

use App\Data\Casts\MoneyInput;
use App\Enums\MembershipType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class CreateSupplierCostData extends Data
{
    public function __construct(
        public int $product_id,
        public int $supplier_id,
        public string $cost_basis,
        #[WithCast(MoneyInput::class)]
        public int $cost,
        public string $currency_code,
        public ?int $store_id = null,
        public ?string $supplier_sku = null,
        public ?int $lead_time_days = null,
        public string $min_order_quantity = '1.00',
        public bool $is_preferred = false,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $costBases = array_keys(config('supplier_costs.cost_bases', []));

        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
            'cost_basis' => ['required', 'string', Rule::in($costBases)],
            'supplier_sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'min_order_quantity' => ['required', 'numeric', 'min:0.01'],
            'is_preferred' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

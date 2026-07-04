<?php

namespace App\Data\PurchaseOrders;

use App\Enums\MembershipType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreatePurchaseOrderData extends Data
{
    public function __construct(
        public int $supplier_id,
        public int $store_id,
        public string $po_type = 'purchase',
        public ?int $opportunity_id = null,
        public ?int $shortage_resolution_id = null,
        public ?string $currency = null,
        public ?string $expected_at = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?string $supplier_reference = null,
        public int $rounding_adjustment = 0,
        /** @var list<array<string, mixed>>|null */
        public ?array $lines = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(ValidationContext $context): array
    {
        $poTypes = array_keys(config('purchase_orders.types', []));

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where('membership_type', MembershipType::Organisation->value),
            ],
            'store_id' => ['required', 'integer', Rule::exists('stores', 'id')],
            'po_type' => ['required', 'string', Rule::in($poTypes)],
            'opportunity_id' => ['nullable', 'integer', Rule::exists('opportunities', 'id')],
            'shortage_resolution_id' => ['nullable', 'integer', Rule::exists('shortage_resolutions', 'id')],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'rounding_adjustment' => ['nullable', 'integer'],
            'lines' => ['nullable', 'array'],
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'integer', 'min:0'],
            'lines.*.tax' => ['nullable', 'integer', 'min:0'],
            'lines.*.product_tax_class_id' => ['nullable', 'integer', Rule::exists('product_tax_classes', 'id')],
        ];
    }
}

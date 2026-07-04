<?php

namespace App\Data\VirtualStock;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class CreateVirtualStockIntakeData extends Data
{
    public function __construct(
        public int $product_id,
        public int $store_id,
        public int $quantity,
        public string $available_from,
        public string $available_until,
        public int $unit_cost,
        public ?string $currency_code = null,
        public ?int $purchase_order_id = null,
        public ?int $purchase_order_item_id = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'available_from' => ['required', 'date'],
            'available_until' => ['required', 'date', 'after:available_from'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

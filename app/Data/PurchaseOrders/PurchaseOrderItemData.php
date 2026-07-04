<?php

namespace App\Data\PurchaseOrders;

use App\Models\PurchaseOrderItem;
use Spatie\LaravelData\Data;

class PurchaseOrderItemData extends Data
{
    public function __construct(
        public int $id,
        public int $purchase_order_id,
        public ?int $product_id,
        public string $description,
        public string $quantity,
        public string $received_quantity,
        public string $unit_cost,
        public string $tax,
        public ?int $product_tax_class_id,
        public string $total,
        public ?int $supplier_cost_id,
        public int $sort_order,
    ) {}

    public static function fromModel(PurchaseOrderItem $item): self
    {
        return new self(
            id: $item->id,
            purchase_order_id: $item->purchase_order_id,
            product_id: $item->product_id,
            description: $item->description,
            quantity: (string) $item->quantity,
            received_quantity: (string) $item->received_quantity,
            unit_cost: $item->formatMoneyCost('unit_cost'),
            tax: $item->formatMoneyCost('tax'),
            product_tax_class_id: $item->product_tax_class_id,
            total: $item->formatMoneyCost('total'),
            supplier_cost_id: $item->supplier_cost_id,
            sort_order: $item->sort_order,
        );
    }
}

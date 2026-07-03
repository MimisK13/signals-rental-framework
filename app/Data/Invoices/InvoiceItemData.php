<?php

namespace App\Data\Invoices;

use App\Models\InvoiceItem;
use Spatie\LaravelData\Data;

/**
 * API representation of an invoice line item.
 */
class InvoiceItemData extends Data
{
    public function __construct(
        public int $id,
        public int $invoice_id,
        public ?int $opportunity_item_id,
        public ?int $product_id,
        public string $description,
        public string $quantity,
        public ?string $invoiced_quantity,
        public string $unit_price,
        public string $discount,
        public string $tax,
        public string $total,
        public string $tax_treatment,
        public ?int $product_tax_class_id,
        public int $sort_order,
    ) {}

    public static function fromModel(InvoiceItem $item): self
    {
        return new self(
            id: $item->id,
            invoice_id: $item->invoice_id,
            opportunity_item_id: $item->opportunity_item_id,
            product_id: $item->product_id,
            description: $item->description,
            quantity: (string) $item->quantity,
            invoiced_quantity: $item->invoiced_quantity !== null ? (string) $item->invoiced_quantity : null,
            unit_price: $item->formatMoneyCost('unit_price'),
            discount: $item->formatMoneyCost('discount'),
            tax: $item->formatMoneyCost('tax'),
            total: $item->formatMoneyCost('total'),
            tax_treatment: $item->tax_treatment,
            product_tax_class_id: $item->product_tax_class_id,
            sort_order: $item->sort_order,
        );
    }
}

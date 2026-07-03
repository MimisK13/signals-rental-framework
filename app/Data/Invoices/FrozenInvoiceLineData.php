<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;

/**
 * Immutable invoice line payload frozen at checkout fire-time.
 */
class FrozenInvoiceLineData extends Data
{
    public function __construct(
        public int $invoice_item_id,
        public string $line_key,
        public string $source_type,
        public string $description,
        public string $quantity,
        public int $unit_price,
        public int $discount,
        public int $net_total,
        public int $tax,
        public int $product_tax_class_id,
        public string $tax_treatment,
        public ?int $opportunity_item_id = null,
        public ?int $opportunity_cost_id = null,
        public ?int $product_id = null,
        public ?int $surcharge_id = null,
        public ?int $section_item_id = null,
        public int $sort_order = 0,
        /** Quantity invoiced on this checkout (partial-quantity P36). */
        public ?string $invoiced_quantity = null,
    ) {}

    public function grossTotal(): int
    {
        return $this->net_total + $this->tax;
    }
}

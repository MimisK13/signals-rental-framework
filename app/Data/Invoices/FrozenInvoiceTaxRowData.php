<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;

/**
 * Immutable per-rate-group tax row frozen at checkout fire-time.
 */
class FrozenInvoiceTaxRowData extends Data
{
    public function __construct(
        public int $invoice_tax_id,
        public int $product_tax_class_key,
        public int $taxable_amount,
        public int $tax_amount,
        public string $rate_percentage,
        public string $tax_rate_name,
        public ?int $tax_rate_id = null,
        public ?int $tax_rule_id = null,
        public string $result_type = 'standard',
        public ?string $legal_text = null,
        public ?int $zone_tax_rule_id = null,
    ) {}
}

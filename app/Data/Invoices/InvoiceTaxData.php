<?php

namespace App\Data\Invoices;

use App\Models\InvoiceTax;
use Spatie\LaravelData\Data;

/**
 * API representation of a per-rate tax breakdown row on an invoice.
 */
class InvoiceTaxData extends Data
{
    public function __construct(
        public int $id,
        public int $invoice_id,
        public int $tax_rate_id,
        public string $taxable_amount,
        public string $tax_amount,
        public ?string $rate_percentage,
        public ?string $tax_rate_name,
        public ?string $result_type,
        public ?string $legal_text,
    ) {}

    public static function fromModel(InvoiceTax $tax): self
    {
        return new self(
            id: $tax->id,
            invoice_id: $tax->invoice_id,
            tax_rate_id: $tax->tax_rate_id,
            taxable_amount: $tax->formatMoneyCost('taxable_amount'),
            tax_amount: $tax->formatMoneyCost('tax_amount'),
            rate_percentage: $tax->rate_percentage,
            tax_rate_name: $tax->tax_rate_name,
            result_type: $tax->result_type,
            legal_text: $tax->legal_text,
        );
    }
}

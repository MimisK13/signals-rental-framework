<?php

namespace App\Data\Invoices;

/**
 * Per-line margin snapshot for invoice show / API serialisation.
 */
final class InvoiceLineMarginResult
{
    public function __construct(
        public int $invoice_item_id,
        public bool $has_basis,
        public int $revenue_minor,
        public ?int $cost_basis_minor,
        public ?int $margin_minor,
        public ?string $margin_percent,
    ) {}
}

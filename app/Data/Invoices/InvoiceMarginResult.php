<?php

namespace App\Data\Invoices;

/**
 * Invoice-level margin snapshot with per-line breakdown.
 */
final class InvoiceMarginResult
{
    /**
     * @param  array<int, InvoiceLineMarginResult>  $lines
     */
    public function __construct(
        public int $net_revenue_minor,
        public int $attributable_cost_minor,
        public int $margin_minor,
        public ?string $margin_percent,
        public array $lines,
    ) {}

    public function line(int $invoiceItemId): ?InvoiceLineMarginResult
    {
        return $this->lines[$invoiceItemId] ?? null;
    }
}

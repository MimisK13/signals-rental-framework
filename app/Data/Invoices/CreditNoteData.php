<?php

namespace App\Data\Invoices;

use App\Models\CreditNote;
use Spatie\LaravelData\Data;

/**
 * API / action response for a credit note projection.
 */
class CreditNoteData extends Data
{
    public function __construct(
        public int $id,
        public int $invoice_id,
        public string $number,
        public string $status,
        public string $subtotal,
        public string $tax_total,
        public string $total,
        public string $currency_code,
        public ?string $notes,
        public string $issued_at,
    ) {}

    public static function fromModel(CreditNote $creditNote): self
    {
        return new self(
            id: $creditNote->id,
            invoice_id: $creditNote->invoice_id,
            number: $creditNote->number,
            status: $creditNote->status,
            subtotal: $creditNote->formatMoneyCost('subtotal'),
            tax_total: $creditNote->formatMoneyCost('tax_total'),
            total: $creditNote->formatMoneyCost('total'),
            currency_code: $creditNote->currency_code,
            notes: $creditNote->notes,
            issued_at: $creditNote->issued_at->toIso8601String(),
        );
    }
}

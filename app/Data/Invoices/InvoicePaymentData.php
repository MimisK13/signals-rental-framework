<?php

namespace App\Data\Invoices;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\InvoicePayment;
use Spatie\LaravelData\Data;

/**
 * API representation of a payment or refund row on an invoice.
 */
class InvoicePaymentData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $invoice_id,
        public ?string $number,
        public string $amount,
        public string $payment_method,
        public ?string $reference,
        public ?string $notes,
        public string $paid_at,
        public ?int $created_by,
        public bool $is_refund,
    ) {}

    public static function fromModel(InvoicePayment $payment): self
    {
        return new self(
            id: $payment->id,
            invoice_id: $payment->invoice_id,
            number: $payment->number,
            amount: $payment->formatMoneyCost('amount'),
            payment_method: $payment->payment_method,
            reference: $payment->reference,
            notes: $payment->notes,
            paid_at: self::formatTimestamp($payment->paid_at),
            created_by: $payment->created_by,
            is_refund: $payment->amount < 0,
        );
    }
}

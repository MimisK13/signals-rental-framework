<?php

namespace App\Data\Invoices;

use App\Data\Concerns\FormatsTimestamps;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Spatie\LaravelData\Data;

/**
 * API/serialisation representation of an invoice.
 *
 * Money totals are emitted as decimal strings (RMS format).
 */
class InvoiceData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public string $number,
        public string $type,
        public string $type_label,
        public string $status,
        public string $status_label,
        public int $member_id,
        public int $store_id,
        public ?int $opportunity_id,
        public string $subtotal,
        public string $tax_total,
        public string $total,
        public string $amount_paid,
        public string $amount_outstanding,
        public string $currency_code,
        public string $exchange_rate,
        public bool $prices_include_tax,
        public ?string $notes,
        public ?string $reference,
        /** @var list<string> */
        public array $tag_list,
        public ?string $issued_at,
        public ?string $due_at,
        public ?string $paid_at,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $type = InvoiceType::from($invoice->type);
        $statusLabel = (string) (config('invoices.statuses.'.$invoice->status.'.label') ?? $invoice->status);

        return new self(
            id: $invoice->id,
            number: $invoice->number,
            type: $invoice->type,
            type_label: $type->label(),
            status: $invoice->status,
            status_label: $statusLabel,
            member_id: $invoice->member_id,
            store_id: $invoice->store_id,
            opportunity_id: $invoice->opportunity_id,
            subtotal: $invoice->formatMoneyCost('subtotal'),
            tax_total: $invoice->formatMoneyCost('tax_total'),
            total: $invoice->formatMoneyCost('total'),
            amount_paid: $invoice->formatMoneyCost('amount_paid'),
            amount_outstanding: $invoice->formatMoneyCost('amount_outstanding'),
            currency_code: $invoice->currency_code,
            exchange_rate: $invoice->exchange_rate,
            prices_include_tax: $invoice->prices_include_tax,
            notes: $invoice->notes,
            reference: $invoice->reference,
            tag_list: $invoice->tag_list ?? [],
            issued_at: self::formatNullableTimestamp($invoice->issued_at),
            due_at: self::formatNullableTimestamp($invoice->due_at),
            paid_at: self::formatNullableTimestamp($invoice->paid_at),
            created_at: self::formatNullableTimestamp($invoice->created_at),
            updated_at: self::formatNullableTimestamp($invoice->updated_at),
        );
    }
}

<?php

namespace App\Data\Invoices;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Enums\InvoiceType;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceTax;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

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
        public Lazy|EntityReferenceData|null $member = null,
        public Lazy|EntityReferenceData|null $store = null,
        public Lazy|EntityReferenceData|null $opportunity = null,
        /** @var Lazy|array<int, InvoiceItemData> */
        public Lazy|array $items = [],
        /** @var Lazy|array<int, InvoiceTaxData> */
        public Lazy|array $taxes = [],
        /** @var Lazy|array<int, InvoicePaymentData> */
        public Lazy|array $payments = [],
        /** @var Lazy|array<int, CreditNoteData> */
        public Lazy|array $credit_notes = [],
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
            member: Lazy::whenLoaded('member', $invoice, fn (): ?EntityReferenceData => self::reference($invoice->member)),
            store: Lazy::whenLoaded('store', $invoice, fn (): ?EntityReferenceData => self::reference($invoice->store)),
            opportunity: Lazy::whenLoaded('opportunity', $invoice, fn (): ?EntityReferenceData => self::reference($invoice->opportunity)),
            items: Lazy::whenLoaded(
                'items',
                $invoice,
                fn (): array => $invoice->items
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn (InvoiceItem $item): InvoiceItemData => InvoiceItemData::fromModel($item))
                    ->all(),
            ),
            taxes: Lazy::whenLoaded(
                'taxes',
                $invoice,
                fn (): array => $invoice->taxes
                    ->map(fn (InvoiceTax $tax): InvoiceTaxData => InvoiceTaxData::fromModel($tax))
                    ->all(),
            ),
            payments: Lazy::whenLoaded(
                'payments',
                $invoice,
                fn (): array => $invoice->payments
                    ->sortByDesc('paid_at')
                    ->values()
                    ->map(fn (InvoicePayment $payment): InvoicePaymentData => InvoicePaymentData::fromModel($payment))
                    ->all(),
            ),
            credit_notes: Lazy::whenLoaded(
                'creditNotes',
                $invoice,
                fn (): array => $invoice->creditNotes
                    ->sortByDesc('issued_at')
                    ->values()
                    ->map(fn (CreditNote $creditNote): CreditNoteData => CreditNoteData::fromModel($creditNote))
                    ->all(),
            ),
        );
    }

    private static function reference(Member|Store|Opportunity|null $model): ?EntityReferenceData
    {
        if ($model === null) {
            return null;
        }

        $name = match (true) {
            $model instanceof Opportunity => $model->subject,
            default => $model->name ?? (string) $model->id,
        };

        return new EntityReferenceData(
            id: $model->id,
            name: $name,
        );
    }
}

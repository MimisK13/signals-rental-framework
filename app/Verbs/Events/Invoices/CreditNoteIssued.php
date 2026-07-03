<?php

namespace App\Verbs\Events\Invoices;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteTax;
use App\Models\Invoice;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Issues a credit note against an invoice, projecting credit note rows and
 * updating the invoice balance (credited_total / amount_outstanding).
 *
 * Credit note events live on {@see InvoiceState} — credit notes are projection
 * tables linked to the invoice, not a separate Verbs aggregate. Multiple partial
 * credits are multiple CreditNoteIssued events on the same invoice stream.
 *
 * Amounts on the payload are negated mirrors of the original invoice snapshot,
 * resolved entirely at fire-time in {@see IssueCreditNote}.
 */
class CreditNoteIssued extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public int $credit_note_id,
        public string $number = '',
        public string $credit_note_status = 'issued',
        public string $invoice_status = 'issued',
        public int $credited_total = 0,
        public int $amount_outstanding = 0,
        public int $subtotal = 0,
        public int $tax_total = 0,
        public int $total = 0,
        public ?string $notes = null,
        public string $issued_at = '',
        /** @var list<array<string, mixed>> */
        public array $line_items = [],
        /** @var list<array<string, mixed>> */
        public array $tax_rows = [],
        public ?int $created_by = null,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->credited_total = $this->credited_total;
        $state->amount_outstanding = $this->amount_outstanding;
        $state->status = $this->invoice_status;
        $state->last_event_at = CarbonImmutable::parse($this->issued_at);
    }

    public function handle(InvoiceState $state): void
    {
        $issuedAt = CarbonImmutable::parse($this->issued_at);

        $oldRow = Invoice::query()->whereKey($state->invoice_id)->first();
        $oldValues = $oldRow !== null ? [
            'status' => $oldRow->status,
            'credited_total' => $oldRow->credited_total,
            'amount_outstanding' => $oldRow->amount_outstanding,
        ] : null;

        CreditNote::query()->updateOrCreate(
            ['id' => $this->credit_note_id],
            [
                'invoice_id' => $state->invoice_id,
                'number' => $this->number,
                'status' => $this->credit_note_status,
                'subtotal' => $this->subtotal,
                'tax_total' => $this->tax_total,
                'total' => $this->total,
                'currency_code' => $state->currency_code,
                'exchange_rate' => $state->exchange_rate,
                'prices_include_tax' => $state->prices_include_tax,
                'notes' => $this->notes,
                'issued_at' => $issuedAt,
                'created_at' => $issuedAt,
                'updated_at' => $issuedAt,
            ],
        );

        foreach ($this->line_items as $line) {
            CreditNoteItem::query()->updateOrCreate(
                ['id' => (int) $line['credit_note_item_id']],
                [
                    'credit_note_id' => $this->credit_note_id,
                    'invoice_item_id' => (int) $line['invoice_item_id'],
                    'description' => (string) $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'unit_price' => (int) $line['unit_price'],
                    'discount' => (int) $line['discount'],
                    'tax' => (int) $line['tax'],
                    'total' => (int) $line['total'],
                    'tax_treatment' => (string) $line['tax_treatment'],
                    'product_tax_class_id' => ($line['product_tax_class_id'] ?? 0) > 0
                        ? (int) $line['product_tax_class_id']
                        : null,
                    'sort_order' => (int) ($line['sort_order'] ?? 0),
                    'created_at' => $issuedAt,
                    'updated_at' => $issuedAt,
                ],
            );
        }

        foreach ($this->tax_rows as $taxRow) {
            CreditNoteTax::query()->updateOrCreate(
                ['id' => (int) $taxRow['credit_note_tax_id']],
                [
                    'credit_note_id' => $this->credit_note_id,
                    'invoice_tax_id' => (int) $taxRow['invoice_tax_id'],
                    'tax_rate_id' => ($taxRow['tax_rate_id'] ?? 0) > 0
                        ? (int) $taxRow['tax_rate_id']
                        : null,
                    'taxable_amount' => (int) $taxRow['taxable_amount'],
                    'tax_amount' => (int) $taxRow['tax_amount'],
                    'created_at' => $issuedAt,
                ],
            );
        }

        Invoice::query()
            ->whereKey($state->invoice_id)
            ->update([
                'status' => $state->status,
                'credited_total' => $state->credited_total,
                'amount_outstanding' => $state->amount_outstanding,
                'updated_at' => $issuedAt,
            ]);

        $invoice = Invoice::query()->whereKey($state->invoice_id)->firstOrFail();

        $this->recordInvoiceAudit(
            $invoice,
            'credit_note.issued',
            newValues: [
                'credit_note_id' => $this->credit_note_id,
                'number' => $this->number,
                'credit_note_status' => $this->credit_note_status,
                'invoice_status' => $state->status,
                'credited_total' => $state->credited_total,
                'amount_outstanding' => $state->amount_outstanding,
                'subtotal' => $this->subtotal,
                'tax_total' => $this->tax_total,
                'total' => $this->total,
                'issued_at' => $this->issued_at,
            ],
            oldValues: $oldValues,
        );
    }
}

<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\Opportunity;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for an invoice. Creates the state as a draft document and
 * projects the initial `invoices` row plus optional line/tax rows when
 * generated from an opportunity checkout (P31).
 *
 * `invoice_id` is the application-allocated small primary key. The
 * CreateDraftInvoice / GenerateInvoiceFromOpportunity actions allocate it via
 * SequenceAllocator and pass it in here, so it is persisted in the event payload
 * and replay reproduces identical projection ids (replay-stable).
 */
class InvoiceGenerated extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        public int $invoice_id,
        #[StateId(InvoiceState::class)]
        public ?int $state_id = null,
        public string $number = '',
        public string $type = 'standard',
        public string $status = 'draft',
        public int $member_id = 0,
        public int $store_id = 0,
        public ?int $opportunity_id = null,
        public ?string $currency_code = null,
        public ?string $exchange_rate = null,
        public bool $prices_include_tax = false,
        public int $subtotal = 0,
        public int $tax_total = 0,
        public int $total = 0,
        public ?string $notes = null,
        public ?string $reference = null,
        /** @var list<string> */
        public array $tag_list = [],
        public ?string $generated_at = null,
        /** @var list<array<string, mixed>> */
        public array $line_items = [],
        /** @var list<array<string, mixed>> */
        public array $tax_rows = [],
        public bool $mark_opportunity_invoiced = false,
        public bool $is_posting = true,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->invoice_id = $this->invoice_id;
        $state->number = $this->number;
        $state->type = $this->type;
        $state->status = $this->status;
        $state->is_posting = $this->is_posting;
        $state->member_id = $this->member_id;
        $state->store_id = $this->store_id;
        $state->opportunity_id = $this->opportunity_id;
        $state->currency_code = $this->currency_code ?? 'GBP';
        $state->exchange_rate = $this->exchange_rate ?? '1';
        $state->prices_include_tax = $this->prices_include_tax;
        $state->subtotal = $this->subtotal;
        $state->tax_total = $this->tax_total;
        $state->total = $this->total;
        $state->amount_paid = 0;
        $state->amount_outstanding = $this->total;
        $state->credited_total = 0;
        $state->notes = $this->notes;
        $state->reference = $this->reference;
        $state->tag_list = $this->tag_list;
        $state->generated_at = $this->generated_at !== null
            ? CarbonImmutable::parse($this->generated_at)
            : null;
        $state->last_event_at = $state->generated_at;
    }

    public function handle(InvoiceState $state): void
    {
        Invoice::query()->updateOrCreate(
            ['id' => $state->invoice_id],
            [
                'state_id' => $state->id,
                'number' => $state->number,
                'type' => $state->type,
                'is_posting' => $state->is_posting,
                'status' => $state->status,
                'is_locked' => $state->is_locked,
                'member_id' => $state->member_id,
                'store_id' => $state->store_id,
                'opportunity_id' => $state->opportunity_id,
                'issued_at' => $state->issued_at,
                'due_at' => $state->due_at,
                'paid_at' => $state->paid_at,
                'subtotal' => $state->subtotal,
                'tax_total' => $state->tax_total,
                'total' => $state->total,
                'amount_paid' => $state->amount_paid,
                'amount_outstanding' => $state->amount_outstanding,
                'credited_total' => $state->credited_total,
                'currency_code' => $state->currency_code,
                'exchange_rate' => $state->exchange_rate,
                'prices_include_tax' => $state->prices_include_tax,
                'notes' => $state->notes,
                'reference' => $state->reference,
                'tag_list' => $state->tag_list,
                'created_at' => $state->generated_at,
                'updated_at' => $state->generated_at,
            ],
        );

        foreach ($this->line_items as $line) {
            InvoiceItem::query()->updateOrCreate(
                ['id' => (int) $line['invoice_item_id']],
                [
                    'invoice_id' => $state->invoice_id,
                    'opportunity_item_id' => $line['opportunity_item_id'] ?? null,
                    'product_id' => $line['product_id'] ?? null,
                    'description' => (string) $line['description'],
                    'quantity' => (string) $line['quantity'],
                    'unit_price' => (int) $line['unit_price'],
                    'discount' => (int) $line['discount'],
                    'tax' => (int) $line['tax'],
                    'total' => (int) $line['net_total'],
                    'tax_treatment' => (string) $line['tax_treatment'],
                    'product_tax_class_id' => ($line['product_tax_class_id'] ?? 0) > 0
                        ? (int) $line['product_tax_class_id']
                        : null,
                    'currency_code' => $state->currency_code,
                    'exchange_rate' => $state->exchange_rate,
                    'prices_include_tax' => $state->prices_include_tax,
                    'sort_order' => (int) ($line['sort_order'] ?? 0),
                    'created_at' => $state->generated_at,
                    'updated_at' => $state->generated_at,
                ],
            );
        }

        foreach ($this->tax_rows as $taxRow) {
            InvoiceTax::query()->updateOrCreate(
                ['id' => (int) $taxRow['invoice_tax_id']],
                [
                    'invoice_id' => $state->invoice_id,
                    'tax_rate_id' => $taxRow['tax_rate_id'] ?? null,
                    'taxable_amount' => (int) $taxRow['taxable_amount'],
                    'tax_amount' => (int) $taxRow['tax_amount'],
                    'created_at' => $state->generated_at,
                ],
            );
        }

        if ($this->mark_opportunity_invoiced && $this->opportunity_id !== null) {
            Opportunity::query()
                ->whereKey($this->opportunity_id)
                ->update(['invoiced' => true]);
        }

        $invoice = Invoice::query()->whereKey($state->invoice_id)->firstOrFail();

        $this->recordInvoiceAudit(
            $invoice,
            'invoice.created',
            newValues: [
                'id' => $state->invoice_id,
                'number' => $state->number,
                'type' => $state->type,
                'status' => $state->status,
                'member_id' => $state->member_id,
                'store_id' => $state->store_id,
                'opportunity_id' => $state->opportunity_id,
                'total' => $state->total,
            ],
        );
    }
}

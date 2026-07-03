<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Genesis event for an invoice. Creates the state as a draft document and
 * projects the initial `invoices` row.
 *
 * `invoice_id` is the application-allocated small primary key. The
 * CreateDraftInvoice action allocates it via SequenceAllocator and passes it in
 * here, so it is persisted in the event payload and replay reproduces identical
 * projection ids (replay-stable).
 */
class InvoiceGenerated extends Event
{
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
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->invoice_id = $this->invoice_id;
        $state->number = $this->number;
        $state->type = $this->type;
        $state->status = $this->status;
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
                'status' => $state->status,
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
    }
}

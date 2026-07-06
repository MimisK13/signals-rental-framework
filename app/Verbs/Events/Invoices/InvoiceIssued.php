<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Issues a draft invoice: stamps issued/due dates and locks the invoice for edits.
 *
 * Opportunity locking is fired as a peer event from {@see IssueInvoice}, not here,
 * so replay never appends duplicate nested events from handle().
 */
class InvoiceIssued extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public string $status = 'issued',
        public ?string $issued_at = null,
        public ?string $due_at = null,
        public bool $is_locked = true,
        public bool $lock_opportunity = false,
    ) {}

    public function validate(InvoiceState $state): void
    {
        $this->assert(
            $state->status === 'draft' && ! $state->is_locked,
            'Only draft invoices can be issued.',
        );
    }

    public function apply(InvoiceState $state): void
    {
        $state->status = $this->status;
        $state->issued_at = $this->issued_at !== null
            ? CarbonImmutable::parse($this->issued_at)
            : null;
        $state->due_at = $this->due_at !== null
            ? CarbonImmutable::parse($this->due_at)
            : null;
        $state->is_locked = $this->is_locked;
        $state->last_event_at = $state->issued_at;
    }

    public function handle(InvoiceState $state): void
    {
        $oldRow = Invoice::query()->whereKey($state->invoice_id)->first();
        $oldValues = $oldRow !== null ? [
            'status' => $oldRow->status,
            'issued_at' => $oldRow->issued_at?->toIso8601String(),
            'due_at' => $oldRow->due_at?->toIso8601String(),
            'is_locked' => (bool) $oldRow->is_locked,
        ] : null;

        Invoice::query()
            ->whereKey($state->invoice_id)
            ->update([
                'status' => $state->status,
                'issued_at' => $state->issued_at,
                'due_at' => $state->due_at,
                'is_locked' => $state->is_locked,
                'updated_at' => $state->issued_at,
            ]);

        $invoice = Invoice::query()->whereKey($state->invoice_id)->firstOrFail();

        $this->recordInvoiceAudit(
            $invoice,
            'invoice.issued',
            newValues: [
                'status' => $state->status,
                'issued_at' => $state->issued_at?->toIso8601String(),
                'due_at' => $state->due_at?->toIso8601String(),
                'is_locked' => $state->is_locked,
                'lock_opportunity' => $this->lock_opportunity,
            ],
            oldValues: $oldValues,
        );
    }
}

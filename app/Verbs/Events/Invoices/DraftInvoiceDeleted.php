<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Soft-deletes a draft invoice projection. Replay-safe: handle() is idempotent when
 * the row is already trashed and uses the fire-time {@see $deleted_at} timestamp.
 */
class DraftInvoiceDeleted extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public ?string $deleted_at = null,
        public ?int $deleted_by = null,
    ) {}

    public function validate(InvoiceState $state): void
    {
        $this->assert(
            ! $state->is_deleted,
            'This invoice is already deleted.',
        );

        $this->assert(
            $state->status === 'draft' && ! $state->is_locked,
            'Only draft invoices can be deleted.',
        );
    }

    public function apply(InvoiceState $state): void
    {
        $state->is_deleted = true;
        $state->deleted_at = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : null;
        $state->last_event_at = $state->deleted_at ?? $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        $deletedAt = $this->deleted_at !== null
            ? CarbonImmutable::parse($this->deleted_at)
            : CarbonImmutable::now('UTC');

        $invoice = Invoice::withTrashed()->whereKey($state->invoice_id)->first();

        if ($invoice === null) {
            return;
        }

        if ($invoice->trashed()) {
            return;
        }

        Invoice::withTrashed()
            ->whereKey($state->invoice_id)
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]);

        $invoice = Invoice::withTrashed()->whereKey($state->invoice_id)->firstOrFail();

        $this->recordInvoiceAudit(
            $invoice,
            'invoice.deleted',
            newValues: [
                'deleted_at' => $deletedAt->toIso8601String(),
                'deleted_by' => $this->deleted_by,
            ],
        );
    }
}

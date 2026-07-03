<?php

namespace App\Verbs\Events\Invoices;

use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Event;

/**
 * Stub — credit note issuance lands in P34.
 */
class CreditNoteIssued extends Event
{
    public function __construct(
        public int $credit_note_id,
        public string $status = 'credited',
        public ?string $credited_at = null,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->status = $this->status;
        $state->last_event_at = $this->credited_at !== null
            ? CarbonImmutable::parse($this->credited_at)
            : $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        // P34 — project credit note linkage and status.
    }
}

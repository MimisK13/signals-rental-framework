<?php

namespace App\Verbs\Events\Invoices;

use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Event;

/**
 * Stub — full issue lifecycle lands in P32.
 */
class InvoiceIssued extends Event
{
    public function __construct(
        public string $status = 'issued',
        public ?string $issued_at = null,
        public ?string $due_at = null,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->status = $this->status;
        $state->issued_at = $this->issued_at !== null
            ? CarbonImmutable::parse($this->issued_at)
            : null;
        $state->due_at = $this->due_at !== null
            ? CarbonImmutable::parse($this->due_at)
            : null;
        $state->last_event_at = $state->issued_at;
    }

    public function handle(InvoiceState $state): void
    {
        // P32 — project issued_at/due_at/status to the invoices row.
    }
}

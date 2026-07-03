<?php

namespace App\Verbs\Events\Invoices;

use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Event;

/**
 * Stub — void lifecycle lands in P32.
 */
class InvoiceVoided extends Event
{
    public function __construct(
        public string $status = 'void',
        public ?string $voided_at = null,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->status = $this->status;
        $state->is_void = true;
        $state->last_event_at = $this->voided_at !== null
            ? CarbonImmutable::parse($this->voided_at)
            : $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        // P32 — project void status to the invoices row.
    }
}

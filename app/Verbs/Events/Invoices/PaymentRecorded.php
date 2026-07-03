<?php

namespace App\Verbs\Events\Invoices;

use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Event;

/**
 * Stub — payment recording lands in P33.
 */
class PaymentRecorded extends Event
{
    public function __construct(
        public int $payment_id,
        public int $amount,
        public string $payment_method = 'bank_transfer',
        public ?string $reference = null,
        public ?string $paid_at = null,
        public ?string $status = null,
        public int $amount_paid = 0,
        public int $amount_outstanding = 0,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->amount_paid = $this->amount_paid;
        $state->amount_outstanding = $this->amount_outstanding;

        if ($this->status !== null) {
            $state->status = $this->status;
        }

        if ($this->paid_at !== null) {
            $state->paid_at = CarbonImmutable::parse($this->paid_at);
        }

        $state->last_event_at = $this->paid_at !== null
            ? CarbonImmutable::parse($this->paid_at)
            : $state->last_event_at;
    }

    public function handle(InvoiceState $state): void
    {
        // P33 — project invoice_payments row and header totals.
    }
}

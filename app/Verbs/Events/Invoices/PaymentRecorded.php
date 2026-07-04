<?php

namespace App\Verbs\Events\Invoices;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Verbs\Events\Invoices\Concerns\RecordsInvoiceAudit;
use App\Verbs\States\InvoiceState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records a payment or refund against an invoice, updating balance totals and status.
 *
 * Status, totals, and timestamps are resolved at fire-time and carried on the payload
 * so apply() stays pure and replay-stable.
 */
class PaymentRecorded extends Event
{
    use RecordsInvoiceAudit;

    public function __construct(
        #[StateId(InvoiceState::class)]
        public int $state_id,
        public int $payment_id,
        public int $amount,
        public ?string $number = null,
        public string $payment_method = 'bank_transfer',
        public ?string $reference = null,
        public ?string $notes = null,
        public string $received_at = '',
        public string $status = 'issued',
        public int $amount_paid = 0,
        public int $amount_outstanding = 0,
        public ?string $paid_at = null,
        public string $audit_action = 'payment.recorded',
        public ?int $created_by = null,
    ) {}

    public function apply(InvoiceState $state): void
    {
        $state->amount_paid = $this->amount_paid;
        $state->amount_outstanding = $this->amount_outstanding;
        $state->status = $this->status;
        $state->paid_at = $this->paid_at !== null
            ? CarbonImmutable::parse($this->paid_at)
            : null;
        $state->last_event_at = CarbonImmutable::parse($this->received_at);
    }

    public function handle(InvoiceState $state): void
    {
        $receivedAt = CarbonImmutable::parse($this->received_at);

        $oldRow = Invoice::query()->whereKey($state->invoice_id)->first();
        $oldValues = $oldRow !== null ? [
            'status' => $oldRow->status,
            'amount_paid' => $oldRow->amount_paid,
            'amount_outstanding' => $oldRow->amount_outstanding,
            'paid_at' => $oldRow->paid_at?->toIso8601String(),
        ] : null;

        InvoicePayment::query()->updateOrCreate(
            ['id' => $this->payment_id],
            [
                'invoice_id' => $state->invoice_id,
                'number' => $this->resolvedNumber(),
                'amount' => $this->amount,
                'payment_method' => $this->payment_method,
                'reference' => $this->reference,
                'paid_at' => $receivedAt,
                'notes' => $this->notes,
                'created_by' => $this->created_by,
                'created_at' => $receivedAt,
                'updated_at' => $receivedAt,
            ],
        );

        Invoice::query()
            ->whereKey($state->invoice_id)
            ->update([
                'status' => $state->status,
                'amount_paid' => $state->amount_paid,
                'amount_outstanding' => $state->amount_outstanding,
                'paid_at' => $state->paid_at,
                'updated_at' => $receivedAt,
            ]);

        $invoice = Invoice::query()->whereKey($state->invoice_id)->firstOrFail();

        $this->recordInvoiceAudit(
            $invoice,
            $this->audit_action,
            newValues: [
                'payment_id' => $this->payment_id,
                'number' => $this->resolvedNumber(),
                'amount' => $this->amount,
                'payment_method' => $this->payment_method,
                'reference' => $this->reference,
                'received_at' => $this->received_at,
                'status' => $state->status,
                'amount_paid' => $state->amount_paid,
                'amount_outstanding' => $state->amount_outstanding,
                'paid_at' => $state->paid_at?->toIso8601String(),
            ],
            oldValues: $oldValues,
        );
    }

    /**
     * Legacy PaymentRecorded payloads may omit number; treat missing values as null on replay.
     */
    private function resolvedNumber(): ?string
    {
        return isset($this->number) ? $this->number : null;
    }
}

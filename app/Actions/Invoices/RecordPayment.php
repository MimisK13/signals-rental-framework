<?php

namespace App\Actions\Invoices;

use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\InvoiceData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\BillingCommunicationKind;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Services\Invoices\InvoiceStatusResolver;
use App\Services\Invoices\PaymentNumberGenerator;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\PaymentRecorded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment or refund against an issued invoice via PaymentRecorded.
 */
class RecordPayment
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice, RecordPaymentData $data, bool $isRefund = false): InvoiceData
    {
        Gate::authorize('recordPayment', $invoice);

        $signedAmount = $isRefund ? -abs($data->amount) : abs($data->amount);

        if (! $isRefund && $signedAmount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Payment amount must be greater than zero.'],
            ]);
        }

        $receivedAt = CarbonImmutable::now('UTC');
        $auditAction = $isRefund ? 'payment.refunded' : 'payment.recorded';
        $paymentId = app(SequenceAllocator::class)->next('invoice_payments');

        $this->commitVerbs(function () use (
            $invoice,
            $paymentId,
            $signedAmount,
            $data,
            $receivedAt,
            $auditAction,
            $isRefund,
        ): void {
            $invoice = $this->lockModelForMutation($invoice);

            $this->assertPayableStatus($invoice, $isRefund);

            if ($isRefund && $data->amount > $invoice->amount_paid) {
                throw ValidationException::withMessages([
                    'amount' => ['Refund amount cannot exceed the total amount paid on this invoice.'],
                ]);
            }

            if (! $isRefund && $signedAmount > max(0, $invoice->amount_outstanding)) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount exceeds the outstanding balance on this invoice.'],
                ]);
            }

            $newAmountPaid = $invoice->amount_paid + $signedAmount;
            $newAmountOutstanding = $invoice->total - $newAmountPaid - $invoice->credited_total;

            $resolver = app(InvoiceStatusResolver::class);
            $resolvedStatus = $resolver->resolve(
                $invoice->status,
                $newAmountPaid,
                $invoice->total,
                $newAmountOutstanding,
                $invoice->due_at,
                $receivedAt,
            );

            app(InvoiceStatusRegistry::class)->assertKnown($resolvedStatus);
            app(InvoiceStatusRegistry::class)->assertCanTransitionTo($invoice->status, $resolvedStatus);

            $paidAt = $resolvedStatus === 'paid'
                ? $receivedAt->toIso8601String()
                : null;

            $number = app(PaymentNumberGenerator::class)->nextOrCreate(
                (int) $invoice->store_id,
                $receivedAt,
            );

            PaymentRecorded::fire(
                state_id: (int) $invoice->state_id,
                payment_id: $paymentId,
                number: $number,
                amount: $signedAmount,
                payment_method: $data->payment_method->value,
                reference: $data->reference,
                notes: $data->notes,
                received_at: $receivedAt->toIso8601String(),
                status: $resolvedStatus,
                amount_paid: $newAmountPaid,
                amount_outstanding: $newAmountOutstanding,
                paid_at: $paidAt,
                audit_action: $auditAction,
                created_by: auth()->id(),
            );
        });

        $invoice->refresh();

        if (! $isRefund) {
            app(DispatchBillingCustomerCommunication::class)(
                BillingCommunicationKind::Receipt,
                $paymentId,
                (int) $invoice->store_id,
            );
        }

        return InvoiceData::fromModel($invoice);
    }

    private function assertPayableStatus(Invoice $invoice, bool $isRefund): void
    {
        if (in_array($invoice->status, ['draft', 'void', 'credited'], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Payments can only be recorded against issued invoices.'],
            ]);
        }

        if ($isRefund) {
            if (! in_array($invoice->status, ['partially_paid', 'paid'], true)) {
                throw ValidationException::withMessages([
                    'invoice' => ['Refunds can only be recorded against invoices with recorded payments.'],
                ]);
            }

            return;
        }

        if (! in_array($invoice->status, ['issued', 'partially_paid'], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Payments can only be recorded against issued invoices with an outstanding balance.'],
            ]);
        }
    }
}

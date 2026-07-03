<?php

namespace App\Services\Notifications;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;

/**
 * Builds merge-field payloads for billing customer communication emails (P38).
 */
class BillingCommunicationMergeDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $invoice->loadMissing('member');

        return array_merge($this->companyAndContact($invoice->member), [
            'invoice' => $this->invoiceFields($invoice),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forCreditNote(CreditNote $creditNote): array
    {
        $creditNote->loadMissing('invoice.member');

        return array_merge($this->companyAndContact($creditNote->invoice->member), [
            'invoice' => $this->invoiceFields($creditNote->invoice),
            'credit_note' => [
                'number' => $creditNote->number,
                'total' => abs($creditNote->total),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forReceipt(InvoicePayment $payment): array
    {
        $payment->loadMissing('invoice.member');
        $invoice = $payment->invoice;

        return array_merge($this->companyAndContact($invoice->member), [
            'invoice' => $this->invoiceFields($invoice),
            'payment' => [
                'reference' => $payment->reference,
                'amount' => $payment->amount,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forStatement(Member $member, int $closingBalanceMinor = 0): array
    {
        return array_merge($this->companyAndContact($member), [
            'statement' => [
                'closing_balance' => $closingBalanceMinor,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function companyAndContact(Member $member): array
    {
        return [
            'company' => [
                'name' => settings('company.name', config('app.name')),
            ],
            'contact' => [
                'name' => $member->name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceFields(Invoice $invoice): array
    {
        return [
            'number' => $invoice->number,
            'total' => $invoice->total,
            'due_at' => $invoice->due_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Actions\Invoices;

use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\CreditNoteData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Enums\BillingCommunicationKind;
use App\Jobs\GenerateBillingIssueDocumentJob;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\Invoices\CreditNoteNumberGenerator;
use App\Services\Invoices\CreditNoteStatusRegistry;
use App\Services\Invoices\CreditNoteTaxMirror;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Invoices\CreditNoteIssued;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

/**
 * Issues a full or partial credit note against an issued invoice.
 */
class IssueCreditNote
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice, IssueCreditNoteData $data): CreditNoteData
    {
        Gate::authorize('issueCredit', $invoice);

        $this->assertCreditableStatus($invoice);

        $mirror = app(CreditNoteTaxMirror::class)->mirror($invoice, $data->lines);

        $newCreditedTotal = $invoice->credited_total + $mirror['credit_amount'];

        if ($newCreditedTotal > $invoice->total) {
            throw ValidationException::withMessages([
                'invoice' => ['Cumulative credit notes cannot exceed the invoice total.'],
            ]);
        }

        $newAmountOutstanding = $invoice->total - $invoice->amount_paid - $newCreditedTotal;

        $invoiceStatus = $newCreditedTotal >= $invoice->total
            ? 'credited'
            : $invoice->status;

        app(InvoiceStatusRegistry::class)->assertKnown($invoiceStatus);

        if ($invoiceStatus !== $invoice->status) {
            app(InvoiceStatusRegistry::class)->assertCanTransitionTo($invoice->status, $invoiceStatus);
        }

        $creditNoteStatus = app(CreditNoteStatusRegistry::class)->defaultStatus();
        app(CreditNoteStatusRegistry::class)->assertKnown($creditNoteStatus);

        $issuedAt = CarbonImmutable::now('UTC');
        $creditNoteId = app(SequenceAllocator::class)->next('credit_notes');
        $allocator = app(SequenceAllocator::class);

        $linePayloads = array_map(
            function (array $line) use ($allocator): array {
                return array_merge($line, [
                    'credit_note_item_id' => $allocator->next('credit_note_items'),
                ]);
            },
            $mirror['line_items'],
        );

        $taxPayloads = array_map(
            function (array $row) use ($allocator): array {
                return array_merge($row, [
                    'credit_note_tax_id' => $allocator->next('credit_note_taxes'),
                ]);
            },
            $mirror['tax_rows'],
        );

        $number = app(CreditNoteNumberGenerator::class)->nextOrCreate(
            (int) $invoice->store_id,
            $issuedAt,
        );

        $this->commitVerbs(function () use (
            $invoice,
            $creditNoteId,
            $number,
            $creditNoteStatus,
            $invoiceStatus,
            $newCreditedTotal,
            $newAmountOutstanding,
            $mirror,
            $data,
            $issuedAt,
            $linePayloads,
            $taxPayloads,
        ): void {
            CreditNoteIssued::fire(
                state_id: (int) $invoice->state_id,
                credit_note_id: $creditNoteId,
                number: $number,
                credit_note_status: $creditNoteStatus,
                invoice_status: $invoiceStatus,
                credited_total: $newCreditedTotal,
                amount_outstanding: $newAmountOutstanding,
                subtotal: $mirror['subtotal'],
                tax_total: $mirror['tax_total'],
                total: $mirror['total'],
                notes: $data->notes,
                issued_at: $issuedAt->toIso8601String(),
                line_items: $linePayloads,
                tax_rows: $taxPayloads,
                created_by: auth()->id(),
            );
        });

        $creditNote = CreditNote::query()->whereKey($creditNoteId)->firstOrFail();

        $hasTemplate = app(DocumentTemplateResolver::class)->hasActiveTemplate(
            (int) $invoice->store_id,
            'credit_note',
        );

        if (! Verbs::isReplaying() && $hasTemplate) {
            $jobId = (string) Str::uuid();

            Cache::put(self::pdfStatusCacheKey((int) $creditNote->id), [
                'status' => 'queued',
                'job_id' => $jobId,
            ], now()->addHours(1));

            GenerateBillingIssueDocumentJob::dispatch(
                $jobId,
                'credit_note',
                (int) $creditNote->id,
                auth()->id(),
            );
        }

        app(DispatchBillingCustomerCommunication::class)(
            BillingCommunicationKind::CreditNote,
            (int) $creditNote->id,
            (int) $invoice->store_id,
        );

        return CreditNoteData::fromModel($creditNote);
    }

    public static function pdfStatusCacheKey(int $creditNoteId): string
    {
        return "billing-document:credit_note:{$creditNoteId}";
    }

    private function assertCreditableStatus(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['draft', 'void', 'credited'], true)) {
            throw ValidationException::withMessages([
                'invoice' => ['Credit notes can only be issued against issued invoices.'],
            ]);
        }
    }
}

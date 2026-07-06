<?php

namespace App\Services\Notifications;

use App\Models\CommunicationLog;
use App\Models\CreditNote;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store-scoped communication log queries (P29).
 */
class CommunicationLogQuery
{
    /**
     * @return Builder<CommunicationLog>
     */
    public function forUser(?User $user): Builder
    {
        $query = CommunicationLog::query();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($storeIds): void {
            $builder
                ->whereHasMorph(
                    'subject',
                    [Opportunity::class],
                    fn (Builder $subjectQuery) => $subjectQuery->whereIn('store_id', $storeIds),
                )
                ->orWhereNot('subject_type', Opportunity::class);
        });
    }

    /**
     * Communication logs for billing emails tied to an invoice (P56).
     *
     * Billing comms log the member as subject; invoice linkage is via generated
     * document attachments stored on {@see CommunicationLog::$attachments} as a
     * JSON array of string tokens, e.g. {@code ["document:42"]}. Matching uses
     * {@code whereJsonContains} for exact array-element membership (SQLite +
     * PostgreSQL JSON), not substring search — {@code document:5} must not match
     * {@code document:50}.
     *
     * @return Builder<CommunicationLog>
     */
    public function forInvoice(Invoice $invoice, User $user): Builder
    {
        $paymentIds = InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->pluck('id');

        $creditNoteIds = CreditNote::query()
            ->where('invoice_id', $invoice->id)
            ->pluck('id');

        $documentIds = Document::query()
            ->where(function (Builder $builder) use ($invoice, $paymentIds, $creditNoteIds): void {
                $builder->where(function (Builder $invoiceDocuments) use ($invoice): void {
                    $invoiceDocuments
                        ->where('documentable_type', $invoice->getMorphClass())
                        ->where('documentable_id', $invoice->id);
                });

                if ($paymentIds->isNotEmpty()) {
                    $builder->orWhere(function (Builder $receiptDocuments) use ($paymentIds): void {
                        $receiptDocuments
                            ->where('documentable_type', (new InvoicePayment)->getMorphClass())
                            ->whereIn('documentable_id', $paymentIds);
                    });
                }

                if ($creditNoteIds->isNotEmpty()) {
                    $builder->orWhere(function (Builder $creditDocuments) use ($creditNoteIds): void {
                        $creditDocuments
                            ->where('documentable_type', (new CreditNote)->getMorphClass())
                            ->whereIn('documentable_id', $creditNoteIds);
                    });
                }
            })
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            return $this->forUser($user)->whereRaw('1 = 0');
        }

        return $this->forUser($user)
            ->where(function (Builder $builder) use ($documentIds): void {
                foreach ($documentIds as $documentId) {
                    $builder->orWhereJsonContains('attachments', 'document:'.$documentId);
                }
            });
    }

    /**
     * @return Builder<CommunicationLog>
     */
    public function forSubject(string $subjectType, int $subjectId, User $user): Builder
    {
        return $this->forUser($user)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId);
    }
}

<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Models\Invoice;
use App\Services\Invoices\EnsuresInvoiceIsDraft;
use App\Verbs\Events\Invoices\DraftInvoiceDeleted;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Soft-deletes a draft invoice via the DraftInvoiceDeleted event.
 */
class DeleteDraftInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice): void
    {
        Gate::authorize('delete', $invoice);
        app(EnsuresInvoiceIsDraft::class)->assert($invoice);

        $deletedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($invoice, $deletedAt): void {
            DraftInvoiceDeleted::fire(
                state_id: (int) $invoice->state_id,
                deleted_at: $deletedAt->toIso8601String(),
                deleted_by: auth()->id(),
            );
        });
    }
}

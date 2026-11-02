<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Invoices\Data\InvoiceData;
use App\Domain\Invoices\Data\UpdateDraftInvoiceData;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Services\EnsuresInvoiceIsDraft;
use App\Domain\Invoices\Verbs\Events\InvoiceDraftUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Updates draft-only invoice header fields.
 */
class UpdateDraftInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice, UpdateDraftInvoiceData $data): InvoiceData
    {
        Gate::authorize('update', $invoice);
        app(EnsuresInvoiceIsDraft::class)->assert($invoice);

        $updatedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($invoice, $data, $updatedAt): void {
            InvoiceDraftUpdated::fire(
                state_id: (int) $invoice->state_id,
                notes: $data->notes,
                reference: $data->reference,
                updated_at: $updatedAt->toIso8601String(),
                notes_provided: $data->notes_provided,
                reference_provided: $data->reference_provided,
            );
        });

        return InvoiceData::fromModel($invoice->refresh());
    }
}

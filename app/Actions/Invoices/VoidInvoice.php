<?php

namespace App\Actions\Invoices;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Invoices\InvoiceData;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceStatusRegistry;
use App\Verbs\Events\Invoices\InvoiceVoided;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Voids a draft or unpaid invoice via the InvoiceVoided event.
 */
class VoidInvoice
{
    use CommitsVerbsEvents;

    public function __invoke(Invoice $invoice): InvoiceData
    {
        Gate::authorize('void', $invoice);

        if ($invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoices with recorded payments cannot be voided. Issue a credit note instead.'],
            ]);
        }

        $status = 'void';
        app(InvoiceStatusRegistry::class)->assertKnown($status);
        app(InvoiceStatusRegistry::class)->assertCanTransitionTo($invoice->status, $status);

        $voidedAt = CarbonImmutable::now('UTC');

        $invoice->loadMissing('items');

        $quantityReleases = [];

        foreach ($invoice->items as $item) {
            if ($item->opportunity_item_id === null || $item->invoiced_quantity === null) {
                continue;
            }

            $quantityReleases[] = [
                'opportunity_item_id' => (int) $item->opportunity_item_id,
                'quantity' => (string) $item->invoiced_quantity,
            ];
        }

        $this->commitVerbs(function () use ($invoice, $voidedAt, $status, $quantityReleases): void {
            InvoiceVoided::fire(
                state_id: (int) $invoice->state_id,
                status: $status,
                voided_at: $voidedAt->toIso8601String(),
                quantity_releases: $quantityReleases,
            );
        });

        return InvoiceData::fromModel($invoice->refresh());
    }
}

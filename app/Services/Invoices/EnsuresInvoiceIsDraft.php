<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * Rejects mutations once an invoice has left draft (issued / locked).
 */
class EnsuresInvoiceIsDraft
{
    public function assert(Invoice $invoice): void
    {
        if ($invoice->is_locked || $invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'invoice' => ['Only draft invoices can be edited. Issue the invoice first to make it immutable.'],
            ]);
        }
    }
}

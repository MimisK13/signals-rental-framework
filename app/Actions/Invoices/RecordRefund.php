<?php

namespace App\Actions\Invoices;

use App\Data\Invoices\InvoiceData;
use App\Data\Invoices\RecordPaymentData;
use App\Models\Invoice;

/**
 * Thin wrapper that records a refund via {@see RecordPayment}.
 */
class RecordRefund
{
    public function __invoke(Invoice $invoice, RecordPaymentData $data): InvoiceData
    {
        return (new RecordPayment)($invoice, $data, isRefund: true);
    }
}

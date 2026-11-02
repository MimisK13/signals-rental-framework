<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Actions;

use App\Domain\Invoices\Data\InvoiceData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Models\Invoice;

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

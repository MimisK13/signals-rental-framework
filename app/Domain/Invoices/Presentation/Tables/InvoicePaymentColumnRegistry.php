<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Tables;

use App\Domain\Invoices\Models\InvoicePayment;
use App\Views\Column;
use App\Views\ColumnRegistry;

class InvoicePaymentColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'invoice_payments';
    }

    public function modelClass(): string
    {
        return InvoicePayment::class;
    }

    /**
     * Column keys align with {@see InvoicePayment::defineSchema()}.
     *
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('reference')->label('Reference')->filterable(),
            Column::make('invoice_id')->label('Invoice')->filterable()->type('relation'),
            Column::make('payment_method')->label('Payment Method')->filterable()->type('enum'),
            Column::make('amount')->label('Amount')->sortable()->type('money'),
            Column::make('paid_at')->label('Paid At')->sortable()->filterable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['reference', 'invoice_id', 'payment_method', 'paid_at', 'amount'];
    }
}

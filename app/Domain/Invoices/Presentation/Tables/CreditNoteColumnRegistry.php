<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Presentation\Tables;

use App\Domain\Invoices\Models\CreditNote;
use App\Views\Column;
use App\Views\ColumnRegistry;

class CreditNoteColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'credit_notes';
    }

    public function modelClass(): string
    {
        return CreditNote::class;
    }

    /**
     * Column keys align with {@see CreditNote::defineSchema()}.
     *
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('number')->label('Number')->sortable()->filterable(),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('invoice_id')->label('Invoice')->filterable()->type('relation'),
            Column::make('subtotal')->label('Subtotal')->sortable()->type('money'),
            Column::make('tax_total')->label('Tax Total')->sortable()->type('money'),
            Column::make('total')->label('Total')->sortable()->type('money'),
            Column::make('currency_code')->label('Currency')->filterable(),
            Column::make('issued_at')->label('Issued')->sortable()->filterable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['number', 'status', 'invoice_id', 'total', 'issued_at'];
    }
}

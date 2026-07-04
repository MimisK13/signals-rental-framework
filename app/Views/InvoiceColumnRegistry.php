<?php

namespace App\Views;

use App\Models\Invoice;

class InvoiceColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'invoices';
    }

    public function modelClass(): string
    {
        return Invoice::class;
    }

    /**
     * Column keys align with {@see Invoice::defineSchema()}.
     *
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('number')->label('Number')->sortable()->filterable(),
            Column::make('type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('member_id')->label('Member')->filterable()->type('relation'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('subtotal')->label('Subtotal')->sortable()->type('money'),
            Column::make('tax_total')->label('Tax Total')->sortable()->type('money'),
            Column::make('total')->label('Total')->sortable()->type('money'),
            Column::make('amount_paid')->label('Amount Paid')->sortable()->type('money'),
            Column::make('amount_outstanding')->label('Outstanding')->sortable()->type('money'),
            Column::make('credited_total')->label('Credited Total')->sortable()->type('money'),
            Column::make('currency_code')->label('Currency')->filterable(),
            Column::make('issued_at')->label('Issued')->sortable()->filterable()->type('datetime'),
            Column::make('due_at')->label('Due')->sortable()->filterable()->type('datetime'),
            Column::make('paid_at')->label('Paid')->sortable()->filterable()->type('datetime'),
            Column::make('is_locked')->label('Locked')->filterable()->type('boolean'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['number', 'type', 'status', 'member_id', 'store_id', 'total', 'amount_outstanding', 'issued_at'];
    }
}

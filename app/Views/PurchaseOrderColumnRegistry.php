<?php

namespace App\Views;

use App\Models\PurchaseOrder;

class PurchaseOrderColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'purchase_orders';
    }

    public function modelClass(): string
    {
        return PurchaseOrder::class;
    }

    /**
     * Column keys align with {@see PurchaseOrder::defineSchema()}.
     *
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('number')->label('Number')->sortable()->filterable(),
            Column::make('po_type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('supplier_id')->label('Supplier')->filterable()->type('relation'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('subtotal')->label('Subtotal')->sortable()->type('money')->costGated(),
            Column::make('tax_total')->label('Tax Total')->sortable()->type('money')->costGated(),
            Column::make('total')->label('Total')->sortable()->type('money')->costGated(),
            Column::make('currency_code')->label('Currency')->filterable(),
            Column::make('expected_at')->label('Expected')->sortable()->filterable()->type('datetime'),
            Column::make('starts_at')->label('Starts')->sortable()->filterable()->type('datetime'),
            Column::make('ends_at')->label('Ends')->sortable()->filterable()->type('datetime'),
            Column::make('sent_at')->label('Sent')->sortable()->filterable()->type('datetime'),
            Column::make('received_at')->label('Received')->sortable()->filterable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['number', 'po_type', 'status', 'supplier_id', 'store_id', 'total', 'expected_at'];
    }
}

<?php

namespace App\Views;

use App\Models\VirtualStockIntake;

class VirtualStockIntakeColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'virtual_stock_intakes';
    }

    public function modelClass(): string
    {
        return VirtualStockIntake::class;
    }

    /**
     * Column keys align with {@see VirtualStockIntake::defineSchema()}.
     *
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('number')->label('Number')->sortable()->filterable(),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('product_id')->label('Product')->filterable()->type('relation'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('purchase_order_id')->label('Purchase Order')->filterable()->type('relation'),
            Column::make('quantity')->label('Quantity')->sortable(),
            Column::make('unit_cost')->label('Unit Cost')->sortable()->type('money')->costGated(),
            Column::make('currency_code')->label('Currency')->filterable(),
            Column::make('available_from')->label('Available From')->sortable()->filterable()->type('datetime'),
            Column::make('available_until')->label('Available Until')->sortable()->filterable()->type('datetime'),
            Column::make('confirmed_at')->label('Confirmed')->sortable()->filterable()->type('datetime'),
            Column::make('received_at')->label('Received')->sortable()->filterable()->type('datetime'),
            Column::make('returned_at')->label('Returned')->sortable()->filterable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['number', 'status', 'product_id', 'store_id', 'quantity', 'unit_cost', 'available_from'];
    }
}

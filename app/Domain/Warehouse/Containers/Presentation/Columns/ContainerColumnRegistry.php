<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Columns;

use App\Domain\Warehouse\Containers\Models\Container;
use App\Views\Column;
use App\Views\ColumnRegistry;

final class ContainerColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'containers';
    }

    public function modelClass(): string
    {
        return Container::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('barcode')->label('Barcode')->sortable()->filterable(),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('scan_mode')->label('Scan Mode')->filterable()->type('enum'),
            Column::make('is_temporary')->label('Temporary')->sortable()->filterable()->type('boolean'),
            Column::make('product_id')->label('Product')->filterable()->type('relation'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'barcode', 'status', 'is_temporary', 'created_at'];
    }
}

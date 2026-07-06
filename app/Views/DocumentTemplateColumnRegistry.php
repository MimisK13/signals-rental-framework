<?php

namespace App\Views;

use App\Models\DocumentTemplate;

class DocumentTemplateColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'document_templates';
    }

    public function modelClass(): string
    {
        return DocumentTemplate::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('document_type')->label('Document Type')->sortable()->filterable()->type('enum'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('is_default')->label('Default')->sortable()->filterable()->type('boolean'),
            Column::make('is_system')->label('System')->sortable()->filterable()->type('boolean'),
            Column::make('is_active')->label('Active')->sortable()->filterable()->type('boolean'),
            Column::make('version')->label('Version')->sortable(),
            Column::make('last_edited_at')->label('Last Edited')->sortable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'document_type', 'store_id', 'is_default', 'is_active', 'version', 'last_edited_at'];
    }
}

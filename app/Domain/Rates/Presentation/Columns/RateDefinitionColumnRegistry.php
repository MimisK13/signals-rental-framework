<?php

declare(strict_types=1);

namespace App\Domain\Rates\Presentation\Columns;

use App\Domain\Rates\Models\RateDefinition;
use App\Views\Column;
use App\Views\ColumnRegistry;

final class RateDefinitionColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'rate_definitions';
    }

    public function modelClass(): string
    {
        return RateDefinition::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('description')->label('Description')->filterable(),
            Column::make('calculation_strategy')->label('Calculation Strategy')->sortable()->filterable()->type('enum'),
            Column::make('base_period')->label('Base Period')->sortable()->filterable()->type('enum'),
            Column::make('is_preset')->label('Preset')->sortable()->filterable()->type('boolean'),
            Column::make('preset_slug')->label('Preset Slug')->filterable(),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'calculation_strategy', 'base_period', 'is_preset', 'created_at'];
    }
}

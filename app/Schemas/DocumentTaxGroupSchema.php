<?php

namespace App\Schemas;

use App\Contracts\HasSchema;
use App\Services\SchemaBuilder;

/**
 * Field-registry exposure for grouped document tax breakdown rows (P16+ templates).
 */
class DocumentTaxGroupSchema implements HasSchema
{
    public static function defineSchema(SchemaBuilder $builder): void
    {
        $builder->enum('result_type')
            ->label('Result Type')
            ->group('Tax Breakdown')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
        $builder->text('legal_text')
            ->label('Legal Text')
            ->group('Tax Breakdown')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
        $builder->integer('net_total')
            ->label('Net Total')
            ->group('Tax Breakdown')
            ->format('currency')
            ->alignment('right')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
        $builder->integer('tax_total')
            ->label('Tax Total')
            ->group('Tax Breakdown')
            ->format('currency')
            ->alignment('right')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
        $builder->decimal('rate_percentage')
            ->label('Rate %')
            ->group('Tax Breakdown')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
        $builder->string('tax_rate_name')
            ->label('Tax Rate Name')
            ->group('Tax Breakdown')
            ->filterable(false)
            ->sortable(false)
            ->exportable(true);
    }
}

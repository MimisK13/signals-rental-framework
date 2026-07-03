<?php

namespace App\Enums;

enum DiscountRuleScope: string
{
    case PerLine = 'per_line';
    case AggregateProduct = 'aggregate_product';
    case AggregateGroup = 'aggregate_group';

    public function label(): string
    {
        return match ($this) {
            self::PerLine => 'Per line',
            self::AggregateProduct => 'Aggregate product',
            self::AggregateGroup => 'Aggregate group',
        };
    }
}

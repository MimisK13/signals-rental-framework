<?php

namespace App\Enums;

enum StocktakeObservationType: string
{
    case BulkCount = 'bulk_count';
    case SerializedScan = 'serialized_scan';
    case UnresolvedItem = 'unresolved_item';

    public function label(): string
    {
        return match ($this) {
            self::BulkCount => 'Bulk count',
            self::SerializedScan => 'Serialized scan',
            self::UnresolvedItem => 'Unresolved identifier',
        };
    }
}

<?php

namespace App\Enums;

enum AssetExpenseType: string
{
    case Repair = 'repair';
    case Service = 'service';
    case Parts = 'parts';
    case Inspection = 'inspection';
    case Modification = 'modification';
    case Insurance = 'insurance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Repair => 'Repair',
            self::Service => 'Service',
            self::Parts => 'Parts',
            self::Inspection => 'Inspection',
            self::Modification => 'Modification',
            self::Insurance => 'Insurance',
            self::Other => 'Other',
        };
    }
}

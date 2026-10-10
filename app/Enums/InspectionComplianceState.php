<?php

namespace App\Enums;

enum InspectionComplianceState: string
{
    case Unknown = 'unknown';
    case Valid = 'valid';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Valid => 'Valid',
            self::DueSoon => 'Due soon',
            self::Overdue => 'Overdue',
            self::Failed => 'Failed',
        };
    }
}

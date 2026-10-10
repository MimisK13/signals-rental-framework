<?php

namespace App\Enums;

enum UtilizationCompleteness: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Complete',
            self::Partial => 'Partial',
            self::Unavailable => 'Unavailable',
        };
    }
}

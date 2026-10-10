<?php

namespace App\Enums;

enum RepairStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Unrepairable = 'unrepairable';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Unrepairable => 'Unrepairable',
        };
    }
}

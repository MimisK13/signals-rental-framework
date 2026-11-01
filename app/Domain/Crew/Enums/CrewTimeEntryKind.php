<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewTimeEntryKind: string
{
    case Work = 'work';
    case Travel = 'travel';
    case Standby = 'standby';
    case Training = 'training';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Work => 'Work',
            self::Travel => 'Travel',
            self::Standby => 'Standby',
            self::Training => 'Training',
            self::Other => 'Other',
        };
    }
}

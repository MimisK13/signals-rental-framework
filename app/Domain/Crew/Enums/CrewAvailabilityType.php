<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewAvailabilityType: string
{
    case Leave = 'leave';
    case Unavailable = 'unavailable';
    case Training = 'training';
    case Sick = 'sick';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Leave',
            self::Unavailable => 'Unavailable',
            self::Training => 'Training',
            self::Sick => 'Sick',
            self::Other => 'Other',
        };
    }
}

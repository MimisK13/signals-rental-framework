<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewRateType: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Fixed => 'Fixed',
        };
    }
}

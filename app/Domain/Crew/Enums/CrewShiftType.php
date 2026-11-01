<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewShiftType: string
{
    case Setup = 'setup';
    case Show = 'show';
    case Strike = 'strike';
    case Travel = 'travel';
    case Break = 'break';
    case Standby = 'standby';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Setup',
            self::Show => 'Show',
            self::Strike => 'Strike',
            self::Travel => 'Travel',
            self::Break => 'Break',
            self::Standby => 'Standby',
            self::Other => 'Other',
        };
    }
}

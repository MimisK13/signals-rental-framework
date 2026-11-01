<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewShortageStatus: string
{
    case Open = 'open';
    case PartiallyCovered = 'partially_covered';
    case Covered = 'covered';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::PartiallyCovered => 'Partially Covered',
            self::Covered => 'Covered',
        };
    }
}

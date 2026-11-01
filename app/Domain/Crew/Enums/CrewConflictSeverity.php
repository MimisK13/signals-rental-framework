<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewConflictSeverity: string
{
    case Hard = 'hard';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Hard => 'Hard',
            self::Warning => 'Warning',
        };
    }
}

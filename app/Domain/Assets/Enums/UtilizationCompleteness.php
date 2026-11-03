<?php

declare(strict_types=1);

namespace App\Domain\Assets\Enums;

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

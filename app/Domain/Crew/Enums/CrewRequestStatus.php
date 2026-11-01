<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewRequestStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case PartiallyFilled = 'partially_filled';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::PartiallyFilled => 'Partially Filled',
            self::Filled => 'Filled',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Open, self::Cancelled], true),
            self::Open => in_array($target, [self::PartiallyFilled, self::Filled, self::Cancelled, self::Draft], true),
            self::PartiallyFilled => in_array($target, [self::Filled, self::Open, self::Cancelled], true),
            self::Filled => in_array($target, [self::PartiallyFilled, self::Open, self::Cancelled], true),
            self::Cancelled => false,
        };
    }
}

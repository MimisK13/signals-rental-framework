<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewAvailabilityStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Requested => in_array($target, [self::Approved, self::Rejected, self::Cancelled], true),
            self::Approved => $target === self::Cancelled,
            self::Rejected => false,
            self::Cancelled => false,
        };
    }
}

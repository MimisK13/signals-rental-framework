<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewTimesheetStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Locked => 'Locked',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Submitted,
            self::Submitted => in_array($target, [self::Approved, self::Rejected], true),
            self::Rejected => in_array($target, [self::Draft, self::Submitted], true),
            self::Approved => $target === self::Locked,
            self::Locked => false,
        };
    }
}

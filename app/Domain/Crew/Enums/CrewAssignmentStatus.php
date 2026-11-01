<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewAssignmentStatus: string
{
    case Proposed = 'proposed';
    case Invited = 'invited';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Substituted = 'substituted';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Proposed',
            self::Invited => 'Invited',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Substituted => 'Substituted',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Proposed => in_array($target, [self::Invited, self::Cancelled], true),
            self::Invited => in_array($target, [self::Confirmed, self::Rejected, self::Cancelled], true),
            self::Confirmed => in_array($target, [self::Cancelled, self::Substituted], true),
            self::Rejected => false,
            self::Cancelled => false,
            self::Substituted => false,
        };
    }

    /**
     * Statuses that hold the unique active assignment key for a line + member.
     */
    public function holdsActiveAssignmentKey(): bool
    {
        return in_array($this, [self::Proposed, self::Invited, self::Confirmed], true);
    }
}

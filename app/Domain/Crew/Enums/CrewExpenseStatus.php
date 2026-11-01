<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reimbursed = 'reimbursed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Reimbursed => 'Reimbursed',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Submitted,
            self::Submitted => in_array($target, [self::Approved, self::Rejected], true),
            self::Rejected => $target === self::Draft,
            self::Approved => $target === self::Reimbursed,
            self::Reimbursed => false,
        };
    }
}

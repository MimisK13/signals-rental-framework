<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewMemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Inactive, self::Archived], true),
            self::Inactive => in_array($target, [self::Active, self::Archived], true),
            self::Archived => $target === self::Active,
        };
    }
}

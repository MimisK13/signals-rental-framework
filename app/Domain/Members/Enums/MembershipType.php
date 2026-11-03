<?php

declare(strict_types=1);

namespace App\Domain\Members\Enums;

enum MembershipType: string
{
    case User = 'user';
    case Contact = 'contact';
    case Organisation = 'organisation';
    case Venue = 'venue';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Contact => 'Contact',
            self::Organisation => 'Organisation',
            self::Venue => 'Venue',
        };
    }
}

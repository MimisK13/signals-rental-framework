<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewEngagementType: string
{
    case Employee = 'employee';
    case Freelancer = 'freelancer';
    case Contractor = 'contractor';
    case ExternalCrew = 'external_crew';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Freelancer => 'Freelancer',
            self::Contractor => 'Contractor',
            self::ExternalCrew => 'External Crew',
        };
    }
}

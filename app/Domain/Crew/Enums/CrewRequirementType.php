<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewRequirementType: string
{
    case Skill = 'skill';
    case Qualification = 'qualification';

    public function label(): string
    {
        return match ($this) {
            self::Skill => 'Skill',
            self::Qualification => 'Qualification',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewProficiency: string
{
    case Trainee = 'trainee';
    case Competent = 'competent';
    case Expert = 'expert';
    case Lead = 'lead';

    public function label(): string
    {
        return match ($this) {
            self::Trainee => 'Trainee',
            self::Competent => 'Competent',
            self::Expert => 'Expert',
            self::Lead => 'Lead',
        };
    }
}

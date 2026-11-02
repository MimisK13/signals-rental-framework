<?php

declare(strict_types=1);

namespace App\Domain\Assets\Enums;

enum InspectionResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }
}

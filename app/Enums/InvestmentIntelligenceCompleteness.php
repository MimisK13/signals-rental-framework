<?php

namespace App\Enums;

enum InvestmentIntelligenceCompleteness: string
{
    case Complete = 'complete';
    case Partial = 'partial';
    case Insufficient = 'insufficient';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Complete',
            self::Partial => 'Partial',
            self::Insufficient => 'Insufficient',
        };
    }
}

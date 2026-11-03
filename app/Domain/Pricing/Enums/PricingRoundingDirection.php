<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Enums;

enum PricingRoundingDirection: string
{
    case HalfUp = 'half_up';
    case Bankers = 'bankers';
}

<?php

namespace App\Enums;

enum PricingRoundingDirection: string
{
    case HalfUp = 'half_up';
    case Bankers = 'bankers';
}

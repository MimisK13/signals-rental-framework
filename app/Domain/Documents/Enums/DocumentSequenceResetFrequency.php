<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum DocumentSequenceResetFrequency: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';
}

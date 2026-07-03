<?php

namespace App\Enums;

enum DocumentSequenceResetFrequency: string
{
    case Never = 'never';
    case Yearly = 'yearly';
    case Monthly = 'monthly';
}

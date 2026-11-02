<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Void = 'void';
}

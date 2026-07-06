<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
    case Void = 'void';
}

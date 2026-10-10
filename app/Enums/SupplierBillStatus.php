<?php

namespace App\Enums;

enum SupplierBillStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Void => 'Void',
        };
    }

    public function isAuthoritative(): bool
    {
        return $this === self::Issued;
    }
}

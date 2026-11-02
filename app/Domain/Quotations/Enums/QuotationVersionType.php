<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Enums;

enum QuotationVersionType: string
{
    case Original = 'original';
    case Revision = 'revision';
    case Alternative = 'alternative';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'Original',
            self::Revision => 'Revision',
            self::Alternative => 'Alternative',
        };
    }
}

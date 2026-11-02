<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Enums;

enum QuotationItemType: string
{
    case Group = 'group';
    case Product = 'product';
    case Accessory = 'accessory';
    case Service = 'service';
    case Text = 'text';

    public function isPriceable(): bool
    {
        return $this !== self::Group;
    }

    public function label(): string
    {
        return match ($this) {
            self::Group => 'Group',
            self::Product => 'Product',
            self::Accessory => 'Accessory',
            self::Service => 'Service',
            self::Text => 'Free text item',
        };
    }
}

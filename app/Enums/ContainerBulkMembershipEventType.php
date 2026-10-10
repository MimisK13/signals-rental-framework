<?php

namespace App\Enums;

enum ContainerBulkMembershipEventType: string
{
    case Pack = 'pack';
    case Unpack = 'unpack';
    case Move = 'move';
    case Correction = 'correction';
    case Dissolution = 'dissolution';

    public function label(): string
    {
        return match ($this) {
            self::Pack => 'Pack',
            self::Unpack => 'Unpack',
            self::Move => 'Move',
            self::Correction => 'Correction',
            self::Dissolution => 'Dissolution',
        };
    }
}

<?php

namespace App\Enums;

enum AssetLifecycleEventType: string
{
    case MarkedMissing = 'marked_missing';
    case Found = 'found';
    case MarkedLost = 'marked_lost';
    case Retired = 'retired';
    case Reactivated = 'reactivated';
    case Sold = 'sold';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::MarkedMissing => 'Marked missing',
            self::Found => 'Found',
            self::MarkedLost => 'Marked lost',
            self::Retired => 'Retired',
            self::Reactivated => 'Reactivated',
            self::Sold => 'Sold',
            self::Disposed => 'Disposed',
        };
    }
}

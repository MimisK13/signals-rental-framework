<?php

declare(strict_types=1);

namespace App\Domain\Crew\Enums;

enum CrewQualificationStatus: string
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';
    case PendingVerification = 'pending_verification';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::ExpiringSoon => 'Expiring Soon',
            self::Expired => 'Expired',
            self::PendingVerification => 'Pending Verification',
        };
    }
}

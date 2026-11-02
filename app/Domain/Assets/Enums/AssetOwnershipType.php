<?php

declare(strict_types=1);

namespace App\Domain\Assets\Enums;

enum AssetOwnershipType: string
{
    case CompanyOwned = 'company_owned';
    case ExternallyOwned = 'externally_owned';
    case Consignment = 'consignment';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::CompanyOwned => 'Company Owned',
            self::ExternallyOwned => 'Externally Owned',
            self::Consignment => 'Consignment',
            self::Unknown => 'Unknown',
        };
    }
}

<?php

namespace App\Enums;

use App\Services\Invoices\InvoiceNumberGenerator;

enum InvoiceType: string
{
    case Standard = 'standard';
    case Deposit = 'deposit';
    case Proforma = 'proforma';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Deposit => 'Deposit',
            self::Proforma => 'Proforma',
        };
    }

    /**
     * Document sequence key segment for {@see InvoiceNumberGenerator}.
     */
    public function documentSequenceType(): string
    {
        return 'invoice_'.$this->value;
    }

    public function isPosting(): bool
    {
        return match ($this) {
            self::Proforma => false,
            default => true,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::Card => 'Card',
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }
}

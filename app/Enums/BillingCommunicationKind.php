<?php

namespace App\Enums;

enum BillingCommunicationKind: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case Receipt = 'receipt';
    case Statement = 'statement';

    public function notificationTypeKey(): string
    {
        return match ($this) {
            self::Invoice => 'invoice.created',
            self::CreditNote => 'invoice.credit_note',
            self::Receipt => 'invoice.payment_received',
            self::Statement => 'statement.emailed',
        };
    }

    public function documentType(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::CreditNote => 'credit_note',
            self::Receipt => 'receipt',
            self::Statement => 'statement',
        };
    }

    public function resolverType(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::CreditNote => 'credit_note',
            self::Receipt => 'receipt',
            self::Statement => 'statement',
        };
    }

    public function settingsKey(): ?string
    {
        return match ($this) {
            self::Invoice => 'auto_email_on_issue',
            self::CreditNote => 'auto_email_on_credit_note',
            self::Receipt => 'auto_email_on_payment',
            self::Statement => null,
        };
    }
}

<?php

namespace App\Services\Documents;

/**
 * Labels and grouping for entity-number document sequence types on the admin numbering page.
 */
class EntityNumberSchemeCatalog
{
    /** @var list<string> */
    public const STORE_SCOPED_TYPES = [
        'invoice_standard',
        'invoice_deposit',
        'invoice_proforma',
        'credit_note',
        'purchase_order',
        'virtual_stock_intake',
        'opportunity',
    ];

    /** @var list<string> */
    public const GLOBAL_TYPES = [
        'member',
        'invoice_payment',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'invoice_standard' => 'Standard Invoice',
        'invoice_deposit' => 'Deposit Invoice',
        'invoice_proforma' => 'Pro Forma Invoice',
        'credit_note' => 'Credit Note',
        'purchase_order' => 'Purchase Order',
        'virtual_stock_intake' => 'Virtual Stock Intake',
        'opportunity' => 'Opportunity',
        'member' => 'Member',
        'invoice_payment' => 'Payment',
    ];

    /**
     * @return list<string>
     */
    public static function allEntityTypes(): array
    {
        return [...self::STORE_SCOPED_TYPES, ...self::GLOBAL_TYPES];
    }

    public static function labelFor(string $documentType): string
    {
        return self::LABELS[$documentType] ?? $documentType;
    }

    public static function isGlobalType(string $documentType): bool
    {
        return in_array($documentType, self::GLOBAL_TYPES, true);
    }
}

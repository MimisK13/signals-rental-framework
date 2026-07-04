<?php

namespace App\Services\Documents;

/**
 * Maps document sequence types to entity projection tables for numbering guards.
 */
class DocumentSequenceEntityProjection
{
    /** @var list<string> */
    public const ENTITY_DOCUMENT_TYPES = [
        'invoice_standard',
        'invoice_deposit',
        'invoice_proforma',
        'credit_note',
        'purchase_order',
        'virtual_stock_intake',
        'member',
        'invoice_payment',
    ];

    /** @var array<string, string> */
    private const PROJECTION_TABLES = [
        'invoice_standard' => 'invoices',
        'invoice_deposit' => 'invoices',
        'invoice_proforma' => 'invoices',
        'credit_note' => 'credit_notes',
        'purchase_order' => 'purchase_orders',
        'virtual_stock_intake' => 'virtual_stock_intakes',
        'member' => 'members',
        'invoice_payment' => 'invoice_payments',
    ];

    public static function projectionTableFor(string $documentType): ?string
    {
        return self::PROJECTION_TABLES[$documentType] ?? null;
    }

    public static function invoiceTypeForDocumentSequenceType(string $documentType): ?string
    {
        return match ($documentType) {
            'invoice_standard' => 'standard',
            'invoice_deposit' => 'deposit',
            'invoice_proforma' => 'proforma',
            default => null,
        };
    }

    public static function isEntityNumberType(string $documentType): bool
    {
        return array_key_exists($documentType, self::PROJECTION_TABLES);
    }
}

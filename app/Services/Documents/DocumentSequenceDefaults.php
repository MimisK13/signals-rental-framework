<?php

namespace App\Services\Documents;

use App\Enums\InvoiceType;
use App\Models\DocumentSequence;
use App\Models\Store;
use App\Services\Invoices\CreditNoteNumberGenerator;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;

/**
 * Canonical default prefixes and sequence attributes for document numbering.
 *
 * Used when auto-provisioning {@see DocumentSequence} rows on first use and when
 * seeding sequences alongside system templates for a new store.
 */
class DocumentSequenceDefaults
{
    /** @var array<string, string> */
    private const PREFIXES = [
        'quote' => 'QUO-',
        'invoice' => 'INV-',
        'credit_note' => 'CN-',
        'deposit' => 'DEP-',
        'pro_forma' => 'PRO-',
        'delivery_note' => 'DN-',
        'pick_list' => 'PL-',
        'return_list' => 'RL-',
        'statement' => 'STM-',
        'receipt' => 'REC-',
        'purchase_order' => 'PO-',
        'invoice_standard' => 'INV-',
        'invoice_deposit' => 'DEP-',
        'invoice_proforma' => 'PRO-',
    ];

    /**
     * @return list<string>
     */
    public static function seedableDocumentTypes(): array
    {
        $types = array_merge(
            SystemDocumentTemplates::CORE_TYPES,
            [
                InvoiceType::Standard->documentSequenceType(),
                InvoiceType::Deposit->documentSequenceType(),
                InvoiceType::Proforma->documentSequenceType(),
            ],
        );

        return array_values(array_unique($types));
    }

    public static function prefixFor(string $documentType): string
    {
        if (isset(self::PREFIXES[$documentType])) {
            return self::PREFIXES[$documentType];
        }

        if ($documentType === CreditNoteNumberGenerator::DOCUMENT_TYPE) {
            return (string) config('credit_notes.fallback_number_prefix', 'CN-');
        }

        if ($documentType === PurchaseOrderNumberGenerator::DOCUMENT_TYPE) {
            return (string) settings(
                'purchase_orders.fallback_number_prefix',
                config('purchase_orders.fallback_number_prefix', 'PO-'),
            );
        }

        return (string) settings('invoices.fallback_number_prefix', 'INV-');
    }

    /**
     * @return array{
     *     prefix: string,
     *     suffix: string,
     *     include_year: bool,
     *     include_month: bool,
     *     next_number: int,
     *     padding: int,
     *     reset_frequency: string
     * }
     */
    public static function attributes(string $documentType): array
    {
        return [
            'prefix' => self::prefixFor($documentType),
            'suffix' => '',
            'include_year' => true,
            'include_month' => false,
            'next_number' => 1,
            'padding' => 4,
            'reset_frequency' => 'never',
        ];
    }

    public static function ensureExists(int $storeId, string $documentType): DocumentSequence
    {
        return DocumentSequence::query()->firstOrCreate(
            [
                'store_id' => $storeId,
                'document_type' => $documentType,
            ],
            self::attributes($documentType),
        );
    }

    public static function seedForStore(Store $store): void
    {
        foreach (self::seedableDocumentTypes() as $documentType) {
            self::ensureExists((int) $store->id, $documentType);
        }
    }
}

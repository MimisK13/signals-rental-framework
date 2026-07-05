<?php

use App\Services\Documents\DocumentSequenceEntityProjection;
use App\Services\Documents\EntityNumberSchemeCatalog;

it('maps invoice document sequence types to invoice type slugs', function () {
    expect(DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType('invoice_standard'))->toBe('standard')
        ->and(DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType('invoice_deposit'))->toBe('deposit')
        ->and(DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType('invoice_proforma'))->toBe('proforma');
});

it('returns null for non-invoice document sequence types', function () {
    expect(DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType('credit_note'))->toBeNull()
        ->and(DocumentSequenceEntityProjection::invoiceTypeForDocumentSequenceType('purchase_order'))->toBeNull();
});

it('lists every entity number type in store-scoped and global order', function () {
    expect(EntityNumberSchemeCatalog::allEntityTypes())->toBe([
        ...EntityNumberSchemeCatalog::STORE_SCOPED_TYPES,
        ...EntityNumberSchemeCatalog::GLOBAL_TYPES,
    ]);
});

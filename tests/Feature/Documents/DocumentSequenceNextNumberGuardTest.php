<?php

use App\Models\DocumentSequence;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Store;
use App\Services\Documents\DocumentSequenceNextNumberGuard;

it('detects existing member numbers without store scoping', function () {
    Member::factory()->create(['number' => 'M-0005']);
    $sequence = DocumentSequence::factory()->make([
        'store_id' => null,
        'document_type' => 'member',
    ]);

    $method = new ReflectionMethod(DocumentSequenceNextNumberGuard::class, 'numberExistsOnProjection');
    $method->setAccessible(true);

    expect($method->invoke(app(DocumentSequenceNextNumberGuard::class), 'members', $sequence, 'M-0005'))
        ->toBeTrue()
        ->and($method->invoke(app(DocumentSequenceNextNumberGuard::class), 'members', $sequence, 'M-9999'))
        ->toBeFalse();
});

it('detects invoice payment numbers within the sequence store', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $sequence = DocumentSequence::factory()->make([
        'store_id' => $store->id,
        'document_type' => 'invoice_payment',
    ]);

    $invoice = Invoice::factory()->create(['store_id' => $store->id]);
    InvoicePayment::factory()->create([
        'invoice_id' => $invoice->id,
        'number' => 'PAY-0007',
    ]);

    $otherInvoice = Invoice::factory()->create(['store_id' => $otherStore->id]);
    InvoicePayment::factory()->create([
        'invoice_id' => $otherInvoice->id,
        'number' => 'PAY-0007',
    ]);

    $method = new ReflectionMethod(DocumentSequenceNextNumberGuard::class, 'numberExistsOnProjection');
    $method->setAccessible(true);
    $guard = app(DocumentSequenceNextNumberGuard::class);

    expect($method->invoke($guard, 'invoice_payments', $sequence, 'PAY-0007'))->toBeTrue()
        ->and($method->invoke($guard, 'invoice_payments', $sequence, 'PAY-9999'))->toBeFalse();
});

it('scopes invoice number checks by invoice type', function () {
    $store = Store::factory()->create();
    $sequence = DocumentSequence::factory()->make([
        'store_id' => $store->id,
        'document_type' => 'invoice_deposit',
    ]);

    Invoice::factory()->create([
        'store_id' => $store->id,
        'type' => 'standard',
        'number' => 'INV-2026-0050',
    ]);
    Invoice::factory()->create([
        'store_id' => $store->id,
        'type' => 'deposit',
        'number' => 'INV-2026-0050',
    ]);

    $method = new ReflectionMethod(DocumentSequenceNextNumberGuard::class, 'numberExistsOnProjection');
    $method->setAccessible(true);
    $guard = app(DocumentSequenceNextNumberGuard::class);

    expect($method->invoke($guard, 'invoices', $sequence, 'INV-2026-0050'))->toBeTrue()
        ->and($method->invoke(
            $guard,
            'invoices',
            DocumentSequence::factory()->make([
                'store_id' => $store->id,
                'document_type' => 'invoice_standard',
            ]),
            'INV-2026-0050',
        ))->toBeTrue()
        ->and($method->invoke(
            $guard,
            'invoices',
            DocumentSequence::factory()->make([
                'store_id' => $store->id,
                'document_type' => 'invoice_standard',
            ]),
            'INV-2026-9999',
        ))->toBeFalse();
});

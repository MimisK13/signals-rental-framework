<?php

use App\Models\InvoicePayment;

it('uses an application-assigned non-incrementing integer primary key', function () {
    $payment = new InvoicePayment;

    expect($payment->getIncrementing())->toBeFalse()
        ->and($payment->getKeyType())->toBe('int');
});

it('factory create yields a non-null readable id and a queryable row', function () {
    $payment = InvoicePayment::factory()->create();

    expect($payment->id)->not->toBeNull()
        ->and($payment->id)->toBeInt()
        ->and($payment->id)->toBeGreaterThan(0);

    $this->assertDatabaseHas('invoice_payments', ['id' => $payment->id]);

    expect(InvoicePayment::query()->find($payment->id)?->id)->toBe($payment->id);
});

it('persists explicit ids verbatim for replay-stable primary keys', function () {
    $created = InvoicePayment::factory()->create(['id' => 4242]);

    expect($created->id)->toBe(4242);
    $this->assertDatabaseHas('invoice_payments', ['id' => 4242]);
});

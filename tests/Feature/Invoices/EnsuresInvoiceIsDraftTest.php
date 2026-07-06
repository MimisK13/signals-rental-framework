<?php

use App\Models\Invoice;
use App\Models\Store;
use App\Services\Invoices\EnsuresInvoiceIsDraft;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->guard = app(EnsuresInvoiceIsDraft::class);
});

it('allows draft unlocked invoices', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft', 'is_locked' => false]);

    $this->guard->assert($invoice);
})->throwsNoExceptions();

it('rejects issued invoices', function () {
    $invoice = Invoice::factory()->create(['status' => 'issued', 'is_locked' => false]);

    expect(fn () => $this->guard->assert($invoice))
        ->toThrow(ValidationException::class, 'Only draft invoices can be edited');
});

it('rejects locked draft invoices', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft', 'is_locked' => true]);

    expect(fn () => $this->guard->assert($invoice))
        ->toThrow(ValidationException::class, 'Only draft invoices can be edited');
});

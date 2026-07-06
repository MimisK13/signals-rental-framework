<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\VoidInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\Invoices\CreditNoteTaxMirror;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{invoice: Invoice, total: int}
 */
function finlockIssuedInvoice(): array
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $invoice->refresh();

    return [
        'invoice' => $invoice,
        'total' => $invoice->total,
    ];
}

it('rejects a stale full payment after a partial payment already landed', function () {
    ['invoice' => $invoice, 'total' => $total] = finlockIssuedInvoice();

    $partial = intdiv($total, 2);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $partial,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    expect(fn () => (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    ))->toThrow(ValidationException::class, 'outstanding balance');

    $invoice->refresh();

    expect($invoice->amount_paid)->toBe($partial)
        ->and($invoice->status)->toBe('partially_paid')
        ->and(InvoicePayment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('accumulates sequential payments to the correct cumulative amount_paid', function () {
    ['invoice' => $invoice, 'total' => $total] = finlockIssuedInvoice();

    $first = intdiv($total, 3);
    $second = $total - $first;

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $first,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $second,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    );

    $invoice->refresh();

    expect($invoice->amount_paid)->toBe($total)
        ->and($invoice->amount_outstanding)->toBe(0)
        ->and($invoice->status)->toBe('paid')
        ->and(InvoicePayment::query()->where('invoice_id', $invoice->id)->sum('amount'))->toBe($total);
});

it('rejects voiding a paid invoice at fire time', function () {
    ['invoice' => $invoice, 'total' => $total] = finlockIssuedInvoice();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    expect(fn () => (new VoidInvoice)($invoice->refresh()))
        ->toThrow(ValidationException::class, 'recorded payments');
});

it('rejects duplicate invoice_item_id selections in credit note mirror', function () {
    ['invoice' => $invoice] = finlockIssuedInvoice();
    $invoice->load(['items', 'taxes']);
    $item = $invoice->items->firstOrFail();

    expect(fn () => app(CreditNoteTaxMirror::class)->mirror($invoice, [
        ['invoice_item_id' => $item->id, 'quantity' => '0.50'],
        ['invoice_item_id' => $item->id, 'quantity' => '0.50'],
    ]))->toThrow(ValidationException::class, 'more than once');

    expect(fn () => (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => '0.50'],
                ['invoice_item_id' => $item->id, 'quantity' => '0.50'],
            ],
        ]),
    ))->toThrow(ValidationException::class, 'more than once');
});

it('reconstructs payment projections byte-identically after replay', function () {
    ['invoice' => $invoice, 'total' => $total] = finlockIssuedInvoice();

    $partial = intdiv($total, 2);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $partial,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $total - $partial,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    );

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $paymentsBefore = InvoicePayment::query()->orderBy('id')->get()->map(
        fn (InvoicePayment $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    InvoicePayment::query()->delete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $paymentsAfter = InvoicePayment::query()->orderBy('id')->get()->map(
        fn (InvoicePayment $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($paymentsAfter)->toBe($paymentsBefore);
});

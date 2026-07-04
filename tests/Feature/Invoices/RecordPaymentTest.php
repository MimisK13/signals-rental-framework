<?php

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\RecordRefund;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('invoices.due_days', 30);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{invoice: Invoice, total: int}
 */
function issuedInvoicePaymentFixture(): array
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

it('records a partial payment and sets partially_paid status', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    $partial = intdiv($total, 2);

    $result = (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $partial,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'PAY-001',
        ]),
    );

    $invoice->refresh();

    expect($result->status)->toBe('partially_paid')
        ->and($result->amount_paid)->toBe($invoice->formatMoneyCost('amount_paid'))
        ->and($result->amount_outstanding)->toBe($invoice->formatMoneyCost('amount_outstanding'))
        ->and($result->paid_at)->toBeNull();

    $payment = InvoicePayment::query()->where('invoice_id', $invoice->id)->sole();

    expect($payment->amount)->toBe($partial)
        ->and($payment->reference)->toBe('PAY-001');
});

it('records the final payment and sets paid status with paid_at', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    $result = (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    );

    $invoice->refresh();

    expect($result->status)->toBe('paid')
        ->and($result->amount_paid)->toBe($invoice->formatMoneyCost('amount_paid'))
        ->and($result->amount_outstanding)->toBe('0.00')
        ->and($result->paid_at)->toBe('2026-07-03T12:00:00.000Z');
});

it('rejects overpayment with 422', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total + 1,
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );
})->throws(ValidationException::class, 'outstanding balance');

it('rejects payment against draft invoices with 422', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );
})->throws(AuthorizationException::class);

it('rejects payment against void invoices with 422', function () {
    ['invoice' => $invoice] = issuedInvoicePaymentFixture();

    $invoice->update(['status' => 'void']);

    (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );
})->throws(AuthorizationException::class);

it('records a refund that re-opens the balance to issued', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $refunded = (new RecordRefund)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'REF-001',
        ]),
    );

    $invoice->refresh();

    expect($refunded->status)->toBe('issued')
        ->and($refunded->amount_paid)->toBe('0.00')
        ->and($refunded->amount_outstanding)->toBe($invoice->formatMoneyCost('amount_outstanding'))
        ->and($refunded->paid_at)->toBeNull();

    expect(InvoicePayment::query()->where('invoice_id', $invoice->id)->count())->toBe(2);
});

it('records a partial refund and sets partially_paid status', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $partialRefund = intdiv($total, 2);

    $refunded = (new RecordRefund)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $partialRefund,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $invoice->refresh();

    expect($refunded->status)->toBe('partially_paid')
        ->and($refunded->amount_paid)->toBe($invoice->formatMoneyCost('amount_paid'))
        ->and($refunded->amount_outstanding)->toBe($invoice->formatMoneyCost('amount_outstanding'));
});

it('rejects refunds exceeding amount paid with 422', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => intdiv($total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    (new RecordRefund)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );
})->throws(ValidationException::class, 'cannot exceed');

it('maintains a correct running balance across multiple payments', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    $first = intdiv($total, 3);
    $second = $total - $first;

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $first,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $result = (new RecordPayment)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => $second,
            'payment_method' => PaymentMethod::Card->value,
        ]),
    );

    $invoice->refresh();

    expect($result->status)->toBe('paid')
        ->and($result->amount_paid)->toBe($invoice->formatMoneyCost('amount_paid'))
        ->and($result->amount_outstanding)->toBe('0.00');
});

it('forbids users without payments.record permission', function () {
    ['invoice' => $invoice, 'total' => $total] = issuedInvoicePaymentFixture();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('invoices.view');

    $this->actingAs($viewer);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $total,
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );
})->throws(AuthorizationException::class);

it('rejects draft invoice creation path without issue', function () {
    $opportunity = giCreateOrderOpportunity();
    $member = $opportunity->member;
    $store = $opportunity->store;

    $draft = (new CreateDraftInvoice)(CreateDraftInvoiceData::from([
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => 1000,
            'payment_method' => PaymentMethod::Cash->value,
        ]),
    );
})->throws(AuthorizationException::class);

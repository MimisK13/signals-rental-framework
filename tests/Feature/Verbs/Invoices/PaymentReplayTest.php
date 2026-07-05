<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
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

it('reconstructs payment projections byte-identically after replay', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $invoice->refresh();
    $total = $invoice->total;
    $partial = intdiv($total, 2);

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $partial,
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'RPL-001',
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

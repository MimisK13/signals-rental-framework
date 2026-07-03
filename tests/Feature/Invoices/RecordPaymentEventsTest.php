<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\RecordRefund;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Jobs\DeliverWebhook;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Webhook;
use Carbon\CarbonImmutable;
use Database\Seeders\NotificationTypeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(NotificationTypeSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T12:00:00Z'));
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function paymentEventsInvoice(): Invoice
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return $invoice->refresh();
}

it('fires payment.recorded audit and webhook when a payment is recorded', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['payment.recorded'], 'is_active' => true]);

    $invoice = paymentEventsInvoice();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => intdiv($invoice->total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    assertActionLogged('payment.recorded', Invoice::class, $invoice->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'payment.recorded');
});

it('fires payment.refunded audit and webhook when a refund is recorded', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['payment.refunded'], 'is_active' => true]);

    $invoice = paymentEventsInvoice();

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => $invoice->total,
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    (new RecordRefund)(
        $invoice->refresh(),
        RecordPaymentData::from([
            'amount' => intdiv($invoice->total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    assertActionLogged('payment.refunded', Invoice::class, $invoice->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'payment.refunded');
});

<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
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

function creditNoteEventsInvoice(): Invoice
{
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return $invoice->refresh();
}

it('fires credit_note.issued audit and webhook when a credit note is issued', function () {
    Queue::fake();

    Webhook::factory()->create(['events' => ['credit_note.issued'], 'is_active' => true]);

    $invoice = creditNoteEventsInvoice();

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from(['lines' => null]),
    );

    assertActionLogged('credit_note.issued', Invoice::class, $invoice->id, auth()->id());

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->event === 'credit_note.issued');
});

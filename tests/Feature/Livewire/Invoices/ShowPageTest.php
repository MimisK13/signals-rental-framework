<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    $this->actingAs(User::factory()->owner()->create());
});

it('renders invoice show page with line items', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);

    $this->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->number);
});

it('issues an invoice from the show page', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('issue')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe('issued');
});

it('surfaces a notify toast when an owner attempts to issue a non-draft invoice', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $invoice->refresh();

    expect($invoice->status)->toBe('issued');

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('issue')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($invoice->fresh()->status)->toBe('issued');
});

it('does not show issue on a paid invoice for owners', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $invoice->refresh();

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('paymentAmount', $invoice->formatMoneyCost('amount_outstanding'))
        ->call('recordPayment')
        ->assertHasNoErrors();

    $paid = $invoice->fresh();
    expect($paid->status)->toBe('paid');

    Volt::test('invoices.show', ['invoice' => $paid])
        ->assertDontSee('wire:click="issue"');
});

it('records a payment via the modal', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);
    $invoice->refresh();

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('openPaymentModal')
        ->set('paymentAmount', $invoice->formatMoneyCost('amount_outstanding'))
        ->call('recordPayment')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('hides margin column without costs.view permission', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);
    $user->givePermissionTo(['invoices.access', 'invoices.view']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $invoice->store_id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Margin');
});

it('shows margin column with costs.view permission', function () {
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);

    $member = Member::factory()->create();
    $user = User::factory()->create(['member_id' => $member->id]);
    $user->givePermissionTo(['invoices.access', 'invoices.view', 'costs.view']);
    MemberStore::create([
        'member_id' => $member->id,
        'store_id' => $invoice->store_id,
        'created_at' => now(),
    ]);
    $this->actingAs($user);

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->assertSee('Margin');
});

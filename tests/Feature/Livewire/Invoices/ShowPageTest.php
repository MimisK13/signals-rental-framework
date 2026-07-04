<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Enums\PricingRoundingDirection;
use App\Jobs\GenerateBillingIssueDocumentJob;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberStore;
use App\Models\User;
use Database\Seeders\DocumentTemplateSeeder;
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

it('queues PDF generation when download is clicked without an existing document', function () {
    Queue::fake();

    $opportunity = giCreateOrderOpportunity();
    app(DocumentTemplateSeeder::class)->seedForStore($opportunity->store);

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    Volt::test('invoices.show', ['invoice' => $invoice->refresh()])
        ->call('downloadPdf')
        ->assertDispatched('notify');

    Queue::assertPushed(GenerateBillingIssueDocumentJob::class, function (GenerateBillingIssueDocumentJob $job) use ($invoice): bool {
        return $job->sourceType === 'invoice'
            && $job->sourceId === $invoice->id;
    });
});

function issuedInvoiceForShowPage(): Invoice
{
    Queue::fake();
    $opportunity = giCreateOrderOpportunity();
    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
    $invoice = Invoice::query()->with(['items', 'taxes'])->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    return $invoice->refresh()->load(['items', 'taxes', 'creditNotes.items']);
}

it('issues a full credit note from the modal', function () {
    $invoice = issuedInvoiceForShowPage();
    $total = $invoice->total;

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('openCreditNoteModal')
        ->assertSet('fullCreditNote', true)
        ->set('creditNoteNotes', 'Full credit via UI')
        ->call('issueCreditNote')
        ->assertHasNoErrors()
        ->assertSet('showCreditNoteModal', false);

    $invoice->refresh();

    expect($invoice->status)->toBe('credited')
        ->and($invoice->credited_total)->toBe($total)
        ->and(CreditNote::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('issues a partial credit note from the modal with one line quantity', function () {
    $invoice = issuedInvoiceForShowPage();
    $item = $invoice->items->first();
    $halfQty = bcdiv((string) $item->quantity, '2', 2);
    $creditedBefore = $invoice->credited_total;

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('openCreditNoteModal')
        ->set('fullCreditNote', false)
        ->set("creditNoteLines.{$item->id}.quantity", $halfQty)
        ->call('issueCreditNote')
        ->assertHasNoErrors()
        ->assertSet('showCreditNoteModal', false);

    $invoice->refresh();
    $creditNote = CreditNote::query()->with('items')->where('invoice_id', $invoice->id)->sole();

    expect($creditNote->items)->toHaveCount(1)
        ->and($creditNote->items->first()->invoice_item_id)->toBe($item->id)
        ->and((string) $creditNote->items->first()->quantity)->toBe($halfQty)
        ->and($invoice->credited_total)->toBeGreaterThan($creditedBefore)
        ->and($invoice->status)->toBe('issued');
});

it('surfaces validation when partial credit exceeds remaining quantity', function () {
    $invoice = issuedInvoiceForShowPage();
    $item = $invoice->items->first();
    $overQty = bcadd((string) $item->quantity, '1', 2);

    Volt::test('invoices.show', ['invoice' => $invoice])
        ->call('openCreditNoteModal')
        ->set('fullCreditNote', false)
        ->set("creditNoteLines.{$item->id}.quantity", $overQty)
        ->call('issueCreditNote')
        ->assertHasErrors(["creditNoteLines.{$item->id}.quantity"]);

    expect(CreditNote::query()->where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('surfaces cumulative over-credit from the action as a toast', function () {
    $invoice = issuedInvoiceForShowPage();
    $item = $invoice->items->first();
    $halfQty = bcdiv((string) $item->quantity, '2', 2);

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => $halfQty],
            ],
        ]),
    );

    $invoice->refresh();

    Volt::test('invoices.show', ['invoice' => $invoice->load(['items', 'creditNotes.items'])])
        ->call('openCreditNoteModal')
        ->call('issueCreditNote')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(CreditNote::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

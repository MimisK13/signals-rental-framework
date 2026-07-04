<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Enums\PaymentMethod;
use App\Enums\PricingRoundingDirection;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CreditNoteTax;
use App\Models\Invoice;
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

it('reconstructs credit note projections byte-identically after replay', function () {
    $opportunity = giCreateOrderOpportunity();

    $draft = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoice = Invoice::query()->findOrFail($draft->id);
    (new IssueInvoice)($invoice);

    $invoice->refresh();
    $total = $invoice->total;

    (new RecordPayment)(
        $invoice,
        RecordPaymentData::from([
            'amount' => intdiv($total, 2),
            'payment_method' => PaymentMethod::BankTransfer->value,
        ]),
    );

    $item = $invoice->refresh()->items()->first();
    expect($item)->not->toBeNull();

    (new IssueCreditNote)(
        $invoice,
        IssueCreditNoteData::from([
            'lines' => [
                ['invoice_item_id' => $item->id],
            ],
        ]),
    );

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $creditNotesBefore = CreditNote::query()->orderBy('id')->get()->map(
        fn (CreditNote $row): array => $row->getAttributes(),
    )->all();

    $creditNoteItemsBefore = CreditNoteItem::query()->orderBy('id')->get()->map(
        fn (CreditNoteItem $row): array => $row->getAttributes(),
    )->all();

    $creditNoteTaxesBefore = CreditNoteTax::query()->orderBy('id')->get()->map(
        fn (CreditNoteTax $row): array => $row->getAttributes(),
    )->all();

    $eventCountBeforeReplay = verbEventCount();

    CreditNoteTax::query()->delete();
    CreditNoteItem::query()->delete();
    CreditNote::query()->forceDelete();
    Invoice::query()->forceDelete();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $creditNotesAfter = CreditNote::query()->orderBy('id')->get()->map(
        fn (CreditNote $row): array => $row->getAttributes(),
    )->all();

    $creditNoteItemsAfter = CreditNoteItem::query()->orderBy('id')->get()->map(
        fn (CreditNoteItem $row): array => $row->getAttributes(),
    )->all();

    $creditNoteTaxesAfter = CreditNoteTax::query()->orderBy('id')->get()->map(
        fn (CreditNoteTax $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($creditNotesAfter)->toBe($creditNotesBefore)
        ->and($creditNotesBefore)->not->toBeEmpty()
        ->and($creditNotesBefore[0]['store_id'])->toBe($invoicesBefore[0]['store_id'])
        ->and($creditNoteItemsAfter)->toBe($creditNoteItemsBefore)
        ->and($creditNoteTaxesAfter)->toBe($creditNoteTaxesBefore);
});

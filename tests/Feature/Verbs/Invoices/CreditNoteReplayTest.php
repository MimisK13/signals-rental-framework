<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Actions\IssueCreditNote;
use App\Domain\Invoices\Actions\IssueInvoice;
use App\Domain\Invoices\Actions\RecordPayment;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Data\IssueCreditNoteData;
use App\Domain\Invoices\Data\RecordPaymentData;
use App\Domain\Invoices\Enums\PaymentMethod;
use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Invoices\Models\CreditNoteItem;
use App\Domain\Invoices\Models\CreditNoteTax;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use Carbon\CarbonImmutable;
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

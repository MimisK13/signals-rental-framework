<?php

use App\Domain\Invoices\Actions\GenerateDepositInvoice;
use App\Domain\Invoices\Actions\GenerateProformaInvoice;
use App\Domain\Invoices\Data\GenerateDepositInvoiceData;
use App\Domain\Invoices\Data\GenerateProformaInvoiceData;
use App\Domain\Invoices\Enums\PartialInvoiceTaxPolicy;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoiceItem;
use App\Domain\Invoices\Models\InvoiceTax;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Enums\PricingRoundingDirection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('finance.partial_invoice_tax', PartialInvoiceTaxPolicy::Proportional->value);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T14:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('reconstructs deposit invoice byte-identically after replay', function () {
    $opportunity = giCreateQuotationOpportunity();

    $invoice = (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
        'opportunity_id' => $opportunity->id,
        'amount_percentage' => '25',
    ]));

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $itemsBefore = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    $taxesBefore = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesBefore)->toHaveCount(1)
        ->and($invoicesBefore[0]['id'])->toBe($invoice->id)
        ->and($invoicesBefore[0]['type'])->toBe('deposit');

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    expect(Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all())->toBe($invoicesBefore)
        ->and(InvoiceItem::query()->orderBy('id')->get()->map(
            fn (InvoiceItem $row): array => $row->getAttributes(),
        )->all())->toBe($itemsBefore)
        ->and(InvoiceTax::query()->orderBy('id')->get()->map(
            fn (InvoiceTax $row): array => $row->getAttributes(),
        )->all())->toBe($taxesBefore);
});

it('reconstructs pro-forma invoice byte-identically after replay', function () {
    $opportunity = giCreateQuotationOpportunity();

    (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $itemsBefore = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    $taxesBefore = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesBefore[0]['type'])->toBe('proforma')
        ->and((bool) $invoicesBefore[0]['is_posting'])->toBeFalse();

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();
    Opportunity::query()->whereKey($opportunity->id)->update(['invoiced' => false]);

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    expect(Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all())->toBe($invoicesBefore)
        ->and(InvoiceItem::query()->orderBy('id')->get()->map(
            fn (InvoiceItem $row): array => $row->getAttributes(),
        )->all())->toBe($itemsBefore)
        ->and(InvoiceTax::query()->orderBy('id')->get()->map(
            fn (InvoiceTax $row): array => $row->getAttributes(),
        )->all())->toBe($taxesBefore)
        ->and((bool) Opportunity::query()->findOrFail($opportunity->id)->invoiced)->toBeFalse();
});

it('reconstructs both deposit and pro-forma together after replay', function () {
    $opportunity = giCreateQuotationOpportunity();

    (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
        'opportunity_id' => $opportunity->id,
        'amount_percentage' => '25',
    ]));

    (new GenerateProformaInvoice)(GenerateProformaInvoiceData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $allInvoices = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(Invoice::query()->orderBy('id')->get()->map(
            fn (Invoice $row): array => $row->getAttributes(),
        )->all())->toBe($allInvoices);
});

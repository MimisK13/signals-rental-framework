<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use App\Models\Opportunity;
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
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T14:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('reconstructs invoice with lines and taxes byte-identically after replay', function () {
    $opportunity = giCreateOrderOpportunity();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
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

    expect($invoicesBefore)->toHaveCount(1)
        ->and($itemsBefore)->not->toBeEmpty()
        ->and($taxesBefore)->not->toBeEmpty()
        ->and($invoicesBefore[0]['id'])->toBe($invoice->id);

    InvoiceTax::query()->delete();
    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();
    Opportunity::query()->whereKey($opportunity->id)->update(['invoiced' => false]);

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    $invoicesAfter = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $itemsAfter = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    $taxesAfter = InvoiceTax::query()->orderBy('id')->get()->map(
        fn (InvoiceTax $row): array => $row->getAttributes(),
    )->all();

    expect($invoicesAfter)->toBe($invoicesBefore)
        ->and($itemsAfter)->toBe($itemsBefore)
        ->and($taxesAfter)->toBe($taxesBefore)
        ->and((bool) Opportunity::query()->findOrFail($opportunity->id)->invoiced)->toBeTrue();
});

it('keeps P30 draft-only replay green alongside opportunity checkout invoices', function () {
    $opportunity = giCreateOrderOpportunity();

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
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

    expect(verbEventCount())->toBe($eventCountBeforeReplay);

    expect(Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all())->toBe($allInvoices);
});

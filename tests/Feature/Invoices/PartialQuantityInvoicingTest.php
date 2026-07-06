<?php

use App\Actions\Invoices\GenerateDepositInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\PricingRoundingDirection;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-03T15:00:00Z'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{opportunity: Opportunity, item: OpportunityItem}
 */
function p36CreateTenUnitOpportunity(): array
{
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Partial invoice qty',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '10',
        'unit_price' => 1_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));
    $opportunity->refresh();
    $item = $opportunity->items->first();
    expect($item)->not->toBeNull();

    return ['opportunity' => $opportunity, 'item' => $item];
}

it('invoices 3 of 10 leaving 7 invoiceable', function () {
    ['opportunity' => $opportunity, 'item' => $item] = p36CreateTenUnitOpportunity();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '3'],
        ],
    ]));

    $item->refresh();
    $row = Invoice::query()->with('items')->findOrFail($invoice->id);

    expect($item->invoiced_quantity)->toBe('3.00')
        ->and($item->remainingInvoiceQuantity())->toBe('7.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse()
        ->and($opportunity->fresh()->isFullyQuantityInvoiced())->toBeFalse()
        ->and($row->items->first()->invoiced_quantity)->toBe('3.00')
        ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
});

it('closes the opportunity on a second invoice for the remaining 7', function () {
    ['opportunity' => $opportunity, 'item' => $item] = p36CreateTenUnitOpportunity();

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '3'],
        ],
    ]));

    $second = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '7'],
        ],
    ]));

    $item->refresh();
    $fullTotal = (int) $opportunity->charge_including_tax_total;

    expect($item->invoiced_quantity)->toBe('10.00')
        ->and($item->remainingInvoiceQuantity())->toBe('0.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeTrue()
        ->and($opportunity->fresh()->isFullyQuantityInvoiced())->toBeTrue()
        ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->count())->toBe(2)
        ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->sum('total'))->toBe($fullTotal);
});

it('rejects over-invoicing with 422', function () {
    ['opportunity' => $opportunity, 'item' => $item] = p36CreateTenUnitOpportunity();

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '6'],
        ],
    ]));

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '5'],
        ],
    ]));
})->throws(ValidationException::class, 'exceed');

it('supports fractional partial quantities such as 0.5 of 1.5', function () {
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Fractional days',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1.5',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));
    $item = $opportunity->items->first();
    expect($item)->not->toBeNull();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '0.5'],
        ],
    ]));

    $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

    expect($item->fresh()->invoiced_quantity)->toBe('0.50')
        ->and($item->fresh()->remainingInvoiceQuantity())->toBe('1.00')
        ->and($row->items->first()->invoiced_quantity)->toBe('0.50')
        ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
});

it('releases invoiced quantities when an invoice is voided', function () {
    ['opportunity' => $opportunity, 'item' => $item] = p36CreateTenUnitOpportunity();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '4'],
        ],
    ]));

    expect($item->fresh()->invoiced_quantity)->toBe('4.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse();

    (new VoidInvoice)(Invoice::query()->findOrFail($invoice->id));

    expect($item->fresh()->invoiced_quantity)->toBe('0.00')
        ->and($item->fresh()->remainingInvoiceQuantity())->toBe('10.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse();
});

it('does not consume invoiced_quantity on deposit invoices', function () {
    $opportunity = giCreateQuotationOpportunity();
    $item = $opportunity->items->first();
    expect($item)->not->toBeNull();

    (new GenerateDepositInvoice)(GenerateDepositInvoiceData::from([
        'opportunity_id' => $opportunity->id,
        'amount_percentage' => '50',
    ]));

    expect($item->fresh()->invoiced_quantity)->toBe('0.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeFalse();
});

it('rejects a full remaining checkout when nothing is left to invoice', function () {
    $opportunity = giCreateOrderOpportunity();

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));
})->throws(ValidationException::class, 'No remaining quantity');

it('keeps running invoiced_quantity byte-identical after replay', function () {
    ['opportunity' => $opportunity, 'item' => $item] = p36CreateTenUnitOpportunity();

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '3'],
        ],
    ]));

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $item->id, 'quantity' => '7'],
        ],
    ]));

    $itemsBefore = OpportunityItem::query()->orderBy('id')->get()->map(
        fn (OpportunityItem $row): array => $row->getAttributes(),
    )->all();

    $invoicesBefore = Invoice::query()->orderBy('id')->get()->map(
        fn (Invoice $row): array => $row->getAttributes(),
    )->all();

    $invoiceItemsBefore = InvoiceItem::query()->orderBy('id')->get()->map(
        fn (InvoiceItem $row): array => $row->getAttributes(),
    )->all();

    InvoiceItem::query()->delete();
    Invoice::query()->forceDelete();
    OpportunityItem::query()->update(['invoiced_quantity' => 0]);
    Opportunity::query()->whereKey($opportunity->id)->update(['invoiced' => false]);

    $eventCountBeforeReplay = verbEventCount();

    Verbs::replay();

    expect(verbEventCount())->toBe($eventCountBeforeReplay)
        ->and(OpportunityItem::query()->orderBy('id')->get()->map(
            fn (OpportunityItem $row): array => $row->getAttributes(),
        )->all())->toBe($itemsBefore)
        ->and(Invoice::query()->orderBy('id')->get()->map(
            fn (Invoice $row): array => $row->getAttributes(),
        )->all())->toBe($invoicesBefore)
        ->and(InvoiceItem::query()->orderBy('id')->get()->map(
            fn (InvoiceItem $row): array => $row->getAttributes(),
        )->all())->toBe($invoiceItemsBefore);
});

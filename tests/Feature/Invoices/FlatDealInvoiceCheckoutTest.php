<?php

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Invoices\Actions\GenerateInvoiceFromOpportunity;
use App\Domain\Invoices\Data\GenerateInvoiceFromOpportunityData;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Members\Models\Member;
use App\Domain\Pricing\Enums\DealPriceMode;
use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Pricing\Enums\PricingRoundingDirection;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ConvertToOrder;
use App\Domain\Projects\Opportunities\Actions\ConvertToQuotation;
use App\Domain\Projects\Opportunities\Actions\CreateOpportunity;
use App\Domain\Projects\Opportunities\Actions\SetDealPrice;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\CreateOpportunityData;
use App\Domain\Projects\Opportunities\Data\SetDealPriceData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator;
use App\Domain\Stores\Models\Store;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * Opp-10 shape: line nets 3_000 + 25_000 = 28_000, flat deal headline 23_800, tax 4_760.
 *
 * @return array{opportunity: Opportunity, cable: OpportunityItem, parCan: OpportunityItem}
 */
function flatDealOppTenShape(): array
{
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Flat deal checkout',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => 'Demo - XLR Cable 5m',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '2',
        'unit_price' => 1_500,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => 'Demo - LED PAR Can RGBW',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 25_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    $opportunity = Opportunity::findOrFail($created->id);

    (new SetDealPrice)($opportunity, SetDealPriceData::from([
        'deal_total' => '238.00',
        'deal_price_mode' => DealPriceMode::FlatHeadline->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));
    $opportunity->refresh();

    $items = $opportunity->items->sortBy('id')->values();

    expect((int) $opportunity->charge_excluding_tax_total)->toBe(23_800)
        ->and((int) $opportunity->tax_total)->toBe(4_760)
        ->and((int) $opportunity->charge_including_tax_total)->toBe(28_560)
        ->and($items->sum(fn (OpportunityItem $item): int => (int) $item->total))->toBe(28_000);

    return [
        'opportunity' => $opportunity,
        'cable' => $items->first(),
        'parCan' => $items->last(),
    ];
}

it('full checkout of a flat-deal opportunity matches opportunity totals penny-exactly', function () {
    ['opportunity' => $opportunity] = flatDealOppTenShape();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

    expect($row->subtotal)->toBe(23_800)
        ->and($row->tax_total)->toBe(4_760)
        ->and($row->total)->toBe(28_560)
        ->and($row->total)->toBe((int) $opportunity->charge_including_tax_total)
        ->and($row->items->sum('total'))->toBe(23_800)
        ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'))
        ->and((bool) $opportunity->fresh()->invoiced)->toBeTrue();
});

it('partial flat-deal checkout is penny-exact and leaves correct remaining quantities', function () {
    ['opportunity' => $opportunity, 'cable' => $cable, 'parCan' => $parCan] = flatDealOppTenShape();

    $first = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $cable->id, 'quantity' => '1'],
        ],
    ]));

    $firstRow = Invoice::query()->with(['items', 'taxes'])->findOrFail($first->id);

    expect($cable->fresh()->invoiced_quantity)->toBe('1.00')
        ->and($cable->fresh()->remainingInvoiceQuantity())->toBe('1.00')
        ->and($parCan->fresh()->invoiced_quantity)->toBe('0.00')
        ->and($firstRow->items->sum('total'))->toBe($firstRow->subtotal)
        ->and($firstRow->items->sum('tax'))->toBe($firstRow->taxes->sum('tax_amount'))
        ->and($firstRow->subtotal + $firstRow->tax_total)->toBe($firstRow->total);

    $second = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'lines' => [
            ['opportunity_item_id' => $cable->id, 'quantity' => '1'],
            ['opportunity_item_id' => $parCan->id, 'quantity' => '1'],
        ],
    ]));

    $secondRow = Invoice::query()->with(['items', 'taxes'])->findOrFail($second->id);

    expect($cable->fresh()->invoiced_quantity)->toBe('2.00')
        ->and($parCan->fresh()->invoiced_quantity)->toBe('1.00')
        ->and((bool) $opportunity->fresh()->invoiced)->toBeTrue()
        ->and($secondRow->subtotal)->toBe(23_800 - $firstRow->subtotal)
        ->and($secondRow->tax_total)->toBe(4_760 - $firstRow->tax_total)
        ->and($secondRow->total)->toBe(28_560 - $firstRow->total)
        ->and(Invoice::query()->where('opportunity_id', $opportunity->id)->sum('total'))->toBe(28_560)
        ->and($secondRow->items->sum('tax'))->toBe($secondRow->taxes->sum('tax_amount'));
});

it('does not regress standard checkout without a deal price', function () {
    $opportunity = giCreateOrderOpportunity();

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

    expect($row->total)->toBe((int) $opportunity->charge_including_tax_total)
        ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
});

it('does not regress proportional deal price checkout', function () {
    $zones = giWireZoneTaxInfrastructure();
    $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Proportional deal invoice',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ]));
    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name.' B',
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    $opportunity = Opportunity::findOrFail($created->id);

    (new SetDealPrice)($opportunity, SetDealPriceData::from([
        'deal_total' => '150.00',
        'deal_price_mode' => DealPriceMode::Proportional->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

    $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
    ]));

    $row = Invoice::query()->with(['items', 'taxes'])->findOrFail($invoice->id);

    expect($row->subtotal)->toBe((int) $opportunity->charge_excluding_tax_total)
        ->and($row->total)->toBe((int) $opportunity->charge_including_tax_total)
        ->and($row->items->sum('total'))->toBe($row->subtotal)
        ->and($row->items->sum('tax'))->toBe($row->taxes->sum('tax_amount'));
});

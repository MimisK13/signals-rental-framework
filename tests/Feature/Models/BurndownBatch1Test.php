<?php

use App\Models\InboundWebhookLog;
use App\Models\InboundWebhookSource;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\PurchaseOrderItem;
use App\Models\ProductSupplierCost;
use App\Models\StockLevel;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;

it('exposes inbound webhook source logs relation', function () {
    $source = InboundWebhookSource::factory()->create();
    InboundWebhookLog::factory()->create(['inbound_webhook_source_id' => $source->id]);

    expect($source->logs()->count())->toBe(1);
});

it('exposes purchase order item supplier cost relation', function () {
    $cost = ProductSupplierCost::factory()->create();
    $item = PurchaseOrderItem::factory()->create(['supplier_cost_id' => $cost->id]);

    expect($item->supplierCost?->is($cost))->toBeTrue();
});

it('reports virtual stock assets via isVirtualAsset', function () {
    $virtual = StockLevel::factory()->virtual()->create();
    $physical = StockLevel::factory()->bulk()->create();

    expect($virtual->isVirtualAsset())->toBeTrue()
        ->and($physical->isVirtualAsset())->toBeFalse();
});

it('exposes tax zone member taxZone relation', function () {
    $zone = TaxZone::factory()->create();
    $member = TaxZoneMember::factory()->create(['tax_zone_id' => $zone->id]);

    expect($member->taxZone?->is($zone))->toBeTrue();
});

it('identifies class map tax rules via isClassMapRule', function () {
    $rule = TaxRule::factory()->create();

    expect($rule->isClassMapRule())->toBeTrue();
});

it('returns remaining invoice quantities keyed by opportunity item id', function () {
    $opportunity = Opportunity::factory()->order()->create();
    $included = OpportunityItem::factory()->for($opportunity)->create([
        'quantity' => '2.00',
        'invoiced_quantity' => '1.00',
        'is_optional' => false,
    ]);
    OpportunityItem::factory()->for($opportunity)->create([
        'is_optional' => true,
    ]);

    $remaining = $opportunity->fresh()->load('items')->remainingInvoiceQuantitiesByItem();

    expect($remaining)->toHaveKey($included->id)
        ->and($remaining[$included->id])->toBe('1.00')
        ->and($remaining)->toHaveCount(1);
});

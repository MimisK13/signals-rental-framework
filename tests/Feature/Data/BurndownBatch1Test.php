<?php

use App\Data\Invoices\InvoiceData;
use App\Data\Notifications\CommunicationLogData;
use App\Data\Notifications\CommunicationTemplateData;
use App\Data\Notifications\NotificationSettingData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Pricing\PriceCategoryData;
use App\Data\Products\ProductData;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Data\Tax\CreateZoneTaxRuleData;
use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\NotificationAudience;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\Invoice;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\PurchaseOrder;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\TaxZone;

it('maps InvoiceData opportunity reference to null when no opportunity is linked', function () {
    $invoice = Invoice::factory()->create(['opportunity_id' => null]);

    $reference = InvoiceData::fromModel($invoice->fresh()->load('opportunity'))
        ->include('opportunity')
        ->toArray()['opportunity'];

    expect($reference)->toBeNull();
});

it('maps PurchaseOrderData opportunity reference to null when no opportunity is linked', function () {
    $purchaseOrder = PurchaseOrder::factory()->create(['opportunity_id' => null]);

    $reference = PurchaseOrderData::fromModel($purchaseOrder->fresh()->load('opportunity'))
        ->include('opportunity')
        ->toArray()['opportunity'];

    expect($reference)->toBeNull();
});

it('maps OpportunityData store reference to null when no store is linked', function () {
    $opportunity = Opportunity::factory()->create(['store_id' => null]);

    $reference = OpportunityData::fromModel($opportunity->fresh()->load('store'))
        ->include('store')
        ->toArray()['store'];

    expect($reference)->toBeNull();
});

it('maps InvoiceData opportunity reference using the opportunity subject', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'Festival rig']);
    $invoice = Invoice::factory()->create(['opportunity_id' => $opportunity->id]);

    $reference = InvoiceData::fromModel($invoice->fresh()->load('opportunity'))
        ->include('opportunity')
        ->toArray()['opportunity'];

    expect($reference['name'])->toBe('Festival rig');
});

it('maps PurchaseOrderData opportunity reference using the opportunity subject', function () {
    $opportunity = Opportunity::factory()->create(['subject' => 'Warehouse restock']);
    $purchaseOrder = PurchaseOrder::factory()->create(['opportunity_id' => $opportunity->id]);

    $reference = PurchaseOrderData::fromModel($purchaseOrder->fresh()->load('opportunity'))
        ->include('opportunity')
        ->toArray()['opportunity'];

    expect($reference['name'])->toBe('Warehouse restock');
});

it('maps OpportunityData store reference when the store relation is loaded', function () {
    $store = Store::factory()->create(['name' => 'North depot']);
    $opportunity = Opportunity::factory()->create(['store_id' => $store->id]);

    $reference = OpportunityData::fromModel($opportunity->fresh()->load('store'))
        ->include('store')
        ->toArray()['store'];

    expect($reference['name'])->toBe('North depot');
});

it('maps OpportunityItemAssetData stock level reference to null when stock level is absent', function () {
    $asset = OpportunityItemAsset::factory()->create(['stock_level_id' => null]);

    $loaded = OpportunityItemAssetData::fromModel($asset->fresh()->load('stockLevel'))
        ->include('stock_level')
        ->toArray();

    expect($loaded['stock_level'])->toBeNull();
});

it('maps PriceCategoryData with tiers when the tiers relation is loaded', function () {
    $category = PriceCategory::factory()->withDefaultTiers()->create();

    $data = PriceCategoryData::fromModel($category->fresh()->load('tiers'));

    expect($data->tiers)->toHaveCount($category->tiers()->count());
});

it('maps PriceCategoryData without tiers when the tiers relation is not loaded', function () {
    $category = PriceCategory::factory()->withDefaultTiers()->create();

    $data = PriceCategoryData::fromModel($category->fresh());

    expect($data->tiers)->toBe([]);
});

it('maps CommunicationLogData friendly types and preserves null polymorphic types', function () {
    // The polymorphic type columns are NOT NULL in the schema, so build a valid row
    // then null the types in-memory to exercise fromModel()'s null-handling branch.
    $log = CommunicationLog::factory()->create();
    $attributes = $log->getAttributes();
    $attributes['notifiable_type'] = null;
    $attributes['subject_type'] = null;
    $log->setRawAttributes($attributes, sync: true);

    $output = CommunicationLogData::fromModel($log)->toArray();

    expect($output['notifiable_type'])->toBeNull()
        ->and($output['subject_type'])->toBeNull();
});

it('maps CommunicationTemplateData without version history by default', function () {
    $template = CommunicationTemplate::factory()->create();
    CommunicationTemplateVersion::factory()->for($template)->create(['version' => 1]);

    $data = CommunicationTemplateData::fromModel($template->fresh());

    expect($data->versions)->toBeNull();
});

it('maps CommunicationTemplateData with version history when requested', function () {
    $template = CommunicationTemplate::factory()->create();
    CommunicationTemplateVersion::factory()->for($template)->create(['version' => 1]);

    $data = CommunicationTemplateData::fromModel($template->fresh(), includeVersions: true);

    expect($data->versions)->toHaveCount(1)
        ->and($data->versions[0]->version)->toBe(1);
});

it('maps NotificationSettingData for both-audience notification types', function () {
    $type = NotificationType::factory()->create([
        'audience' => NotificationAudience::Both,
        'key' => 'batch1.both.audience',
    ]);

    $data = NotificationSettingData::fromType($type->fresh());

    expect($data->audiences)->toHaveKeys(['internal', 'external']);
});

it('builds TaxZoneRuleMatchRequest from seller and buyer zones', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $request = TaxZoneRuleMatchRequest::fromZones(
        $seller,
        $buyer,
        TaxBuyerType::Business,
        hasTaxId: true,
        taxCategoryId: 42,
    );

    expect($request->seller_zone_id)->toBe($seller->id)
        ->and($request->buyer_zone_id)->toBe($buyer->id)
        ->and($request->has_tax_id)->toBeTrue()
        ->and($request->tax_category_id)->toBe(42);
});

it('maps ProductData without stock levels or rates when those relations are not loaded', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create(['product_id' => $product->id]);
    ProductRate::factory()->for($product)->create();

    $output = ProductData::fromModel($product->fresh())->toArray();

    expect($output['stock_levels'])->toBeNull()
        ->and($output['rates'])->toBeNull();
});

it('maps ProductData stock levels and rates when those relations are loaded', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create(['product_id' => $product->id]);
    ProductRate::factory()->for($product)->create();

    $output = ProductData::fromModel($product->fresh()->load(['stockLevels', 'rates']))->toArray();

    expect($output['stock_levels'])->toHaveCount(1)
        ->and($output['rates'])->toHaveCount(1);
});

it('casts buyer type values through TaxBuyerTypeCast on zone rule data', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $fromAlias = CreateZoneTaxRuleData::from([
        'name' => 'B2B alias',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => 'b2b',
    ]);

    $fromEmpty = CreateZoneTaxRuleData::from([
        'name' => 'Empty buyer',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => '',
    ]);

    expect($fromAlias->buyer_type?->value)->toBe('business')
        ->and($fromEmpty->buyer_type)->toBeNull();
});

it('casts enum instances and invalid scalar buyer types through TaxBuyerTypeCast', function () {
    $seller = TaxZone::factory()->create();
    $buyer = TaxZone::factory()->create();

    $fromEnum = CreateZoneTaxRuleData::from([
        'name' => 'Enum buyer',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => TaxBuyerType::Business,
    ]);

    $fromInvalid = CreateZoneTaxRuleData::from([
        'name' => 'Invalid buyer',
        'seller_zone_id' => $seller->id,
        'buyer_zone_id' => $buyer->id,
        'result_type' => TaxResultType::Standard->value,
        'buyer_type' => 999,
    ]);

    expect($fromEnum->buyer_type)->toBe(TaxBuyerType::Business)
        ->and($fromInvalid->buyer_type)->toBeNull();
});

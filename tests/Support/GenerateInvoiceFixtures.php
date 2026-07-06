<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Enums\TaxBuyerType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\TaxZoneRuleSeeder;

/**
 * @return array{
 *     uk: TaxZone,
 *     euMember: TaxZone,
 *     world: TaxZone,
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass,
 *     productClass2: ProductTaxClass,
 *     taxRate: TaxRate
 * }
 */
function giWireZoneTaxInfrastructure(): array
{
    $uk = TaxZone::factory()->uk()->create();
    $euMember = TaxZone::factory()->euMember()->create();
    $world = TaxZone::factory()->world()->create();

    TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $euMember->id, 'country_code' => 'DE']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $world->id, 'country_code' => 'US']);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $productClass2 = ProductTaxClass::factory()->create();
    $taxRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);
    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass2->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    (new TaxZoneRuleSeeder)->run();

    return compact('uk', 'euMember', 'world', 'orgClass', 'productClass', 'productClass2', 'taxRate');
}

/**
 * @param  array<string, mixed>  $memberAttrs
 * @param  array<string, mixed>  $oppAttrs
 * @param  array<string, mixed>|null  $zones
 */
function giCreateOrderOpportunity(array $memberAttrs = [], array $oppAttrs = [], ?array $zones = null): Opportunity
{
    $zones ??= giWireZoneTaxInfrastructure();

    $store = Store::factory()->create([
        'tax_zone_id' => $zones['uk']->id,
        'country_code' => 'GB',
    ]);
    $member = Member::factory()->organisation()->create(array_merge([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
        'buyer_type' => TaxBuyerType::Individual->value,
    ], $memberAttrs));

    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
        'subject' => 'Checkout test',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ], $oppAttrs)));

    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    (new ConvertToOrder)(Opportunity::findOrFail($created->id));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

    return $opportunity->fresh(['items', 'costs', 'member', 'store']);
}

/**
 * @param  array<string, mixed>  $memberAttrs
 * @param  array<string, mixed>  $oppAttrs
 * @param  array<string, mixed>|null  $zones
 */
function giCreateQuotationOpportunity(array $memberAttrs = [], array $oppAttrs = [], ?array $zones = null): Opportunity
{
    $zones ??= giWireZoneTaxInfrastructure();

    $store = Store::factory()->create([
        'tax_zone_id' => $zones['uk']->id,
        'country_code' => 'GB',
    ]);
    $member = Member::factory()->organisation()->create(array_merge([
        'sale_tax_class_id' => $zones['orgClass']->id,
        'tax_zone_id' => $zones['uk']->id,
        'buyer_type' => TaxBuyerType::Individual->value,
    ], $memberAttrs));

    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge([
        'subject' => 'Deposit quotation',
        'member_id' => $member->id,
        'store_id' => $store->id,
    ], $oppAttrs)));

    (new ConvertToQuotation)(Opportunity::findOrFail($created->id));

    $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

    (new AddOpportunityItem)(Opportunity::findOrFail($created->id), AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '1',
        'unit_price' => 10_000,
        'transaction_type' => LineItemTransactionType::Sale->value,
    ]));

    $opportunity = Opportunity::query()->findOrFail($created->id);
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->fresh(['items.itemSurcharges', 'costs', 'member']));

    return $opportunity->fresh(['items', 'costs', 'member', 'store']);
}

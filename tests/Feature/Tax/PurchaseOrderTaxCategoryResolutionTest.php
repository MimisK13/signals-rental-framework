<?php

use App\Enums\PricingRoundingDirection;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\Member;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxCategory;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\TaxZone;
use App\Models\TaxZoneMember;
use App\Models\User;
use App\Services\PurchaseOrders\PurchaseOrderTaxCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{
 *     uk: TaxZone,
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass,
 *     reducedCategory: TaxCategory,
 * }
 */
function purchaseTaxCategoryInfrastructure(): array
{
    $uk = TaxZone::factory()->uk()->create();
    TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);
    $reducedCategory = TaxCategory::factory()->reduced()->create();

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $uk->id,
        'buyer_type' => null,
        'has_tax_id' => null,
        'tax_category_id' => $reducedCategory->id,
        'result_type' => TaxResultType::ZeroRated,
        'priority' => 100,
        'name' => 'Reduced purchase category zero rated',
    ])->create();

    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $uk->id,
        'buyer_type' => null,
        'has_tax_id' => null,
        'tax_category_id' => null,
        'result_type' => TaxResultType::Standard,
        'priority' => 50,
        'name' => 'Wildcard purchase standard',
    ])->create();

    return compact('uk', 'orgClass', 'productClass', 'reducedCategory');
}

describe('purchase tax category through PurchaseOrderTaxCalculator', function () {
    it('applies different zone treatments per product purchase tax category', function () {
        $infra = purchaseTaxCategoryInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $infra['uk']->id, 'country_code' => 'GB']);
        $supplier = Member::factory()->organisation()->create([
            'purchase_tax_class_id' => $infra['orgClass']->id,
            'tax_zone_id' => $infra['uk']->id,
            'buyer_type' => TaxBuyerType::Business->value,
        ]);

        $reducedProduct = Product::factory()->create([
            'purchase_tax_class_id' => $infra['productClass']->id,
            'purchase_tax_category_id' => $infra['reducedCategory']->id,
        ]);
        $uncategorisedProduct = Product::factory()->create([
            'purchase_tax_class_id' => $infra['productClass']->id,
            'purchase_tax_category_id' => null,
        ]);

        $lines = app(PurchaseOrderTaxCalculator::class)->applyExpectedLineTax(
            supplierId: $supplier->id,
            storeId: $store->id,
            currencyCode: 'GBP',
            lines: [
                [
                    'purchase_order_item_id' => 1,
                    'product_id' => $reducedProduct->id,
                    'quantity' => '1',
                    'unit_cost' => 10_000,
                ],
                [
                    'purchase_order_item_id' => 2,
                    'product_id' => $uncategorisedProduct->id,
                    'quantity' => '1',
                    'unit_cost' => 10_000,
                ],
            ],
        );

        $taxByLine = collect($lines)->keyBy('purchase_order_item_id');

        expect($taxByLine[1]['tax'])->toBe(0)
            ->and($taxByLine[2]['tax'])->toBe(2_000);
    });
});

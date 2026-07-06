<?php

use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\PricingRoundingDirection;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
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
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\Services\Tax\TaxZoneRuleResolver;
use App\ValueObjects\Tax\DocumentTaxSummary;
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
 *     taxRate: TaxRate,
 *     reducedCategory: TaxCategory,
 * }
 */
function productTaxCategoryInfrastructure(): array
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
        'name' => 'Reduced category zero rated',
    ])->create();

    TaxRule::factory()->zoneMatch([
        'seller_zone_id' => $uk->id,
        'buyer_zone_id' => $uk->id,
        'buyer_type' => null,
        'has_tax_id' => null,
        'tax_category_id' => null,
        'result_type' => TaxResultType::Standard,
        'priority' => 50,
        'name' => 'Wildcard standard',
    ])->create();

    return compact('uk', 'orgClass', 'productClass', 'taxRate', 'reducedCategory');
}

/**
 * @param  list<DocumentTaxLineInput>  $lines
 */
function summarizeSaleTaxForOpportunity(Opportunity $opportunity, array $lines): DocumentTaxSummary
{
    $context = TaxResolutionContext::fromOpportunity($opportunity->loadMissing(['member', 'store']));

    return app(TaxResolutionContextHolder::class)->using(
        $context,
        fn (): DocumentTaxSummary => app(DocumentTaxBreakdown::class)->summarize(
            new DocumentTaxRequest(
                lines: $lines,
                currencyCode: $opportunity->currency_code ?: 'GBP',
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $opportunity->starts_at,
            ),
        ),
    );
}

describe('TaxZoneRuleResolver category scoping', function () {
    it('matches category-scoped rules only when the line carries that category', function () {
        $infra = productTaxCategoryInfrastructure();

        $resolver = app(TaxZoneRuleResolver::class);

        $reducedMatch = $resolver->resolve(new TaxZoneRuleMatchRequest(
            seller_zone_id: $infra['uk']->id,
            buyer_zone_id: $infra['uk']->id,
            buyer_type: TaxBuyerType::Individual,
            tax_category_id: $infra['reducedCategory']->id,
        ));

        $uncategorisedMatch = $resolver->resolve(new TaxZoneRuleMatchRequest(
            seller_zone_id: $infra['uk']->id,
            buyer_zone_id: $infra['uk']->id,
            buyer_type: TaxBuyerType::Individual,
            tax_category_id: null,
        ));

        expect($reducedMatch?->result_type)->toBe(TaxResultType::ZeroRated)
            ->and($reducedMatch?->tax_category_id)->toBe($infra['reducedCategory']->id)
            ->and($uncategorisedMatch?->result_type)->toBe(TaxResultType::Standard)
            ->and($uncategorisedMatch?->tax_category_id)->toBeNull();
    });

    it('does not match category-scoped rules for uncategorised lines', function () {
        $infra = productTaxCategoryInfrastructure();

        $matchingIds = app(TaxZoneRuleResolver::class)
            ->matchingQuery(new TaxZoneRuleMatchRequest(
                seller_zone_id: $infra['uk']->id,
                buyer_zone_id: $infra['uk']->id,
                buyer_type: TaxBuyerType::Individual,
                tax_category_id: null,
            ))
            ->pluck('tax_category_id')
            ->all();

        expect($matchingIds)->not->toContain($infra['reducedCategory']->id);
    });
});

describe('sale tax category through opportunity rollup', function () {
    it('applies different zone treatments per product sale tax category', function () {
        $infra = productTaxCategoryInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $infra['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $infra['orgClass']->id,
            'tax_zone_id' => $infra['uk']->id,
            'buyer_type' => TaxBuyerType::Individual->value,
        ]);

        $reducedProduct = Product::factory()->sale()->create([
            'tax_class_id' => $infra['productClass']->id,
            'sale_tax_category_id' => $infra['reducedCategory']->id,
        ]);
        $uncategorisedProduct = Product::factory()->sale()->create([
            'tax_class_id' => $infra['productClass']->id,
            'sale_tax_category_id' => null,
        ]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'currency_code' => 'GBP',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $reducedProduct->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $uncategorisedProduct->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe(2_000)
            ->and($opportunity->charge_including_tax_total)->toBe(22_000);

        $summary = summarizeSaleTaxForOpportunity($opportunity, [
            new DocumentTaxLineInput(
                lineKey: 'item:reduced',
                netMinor: 10_000,
                productTaxClassId: $infra['productClass']->id,
                productTaxCategoryId: $infra['reducedCategory']->id,
            ),
            new DocumentTaxLineInput(
                lineKey: 'item:uncategorised',
                netMinor: 10_000,
                productTaxClassId: $infra['productClass']->id,
                productTaxCategoryId: null,
            ),
        ]);

        $taxByLine = collect($summary->lineAllocations)->keyBy('lineKey');

        expect($taxByLine['item:reduced']->taxMinor)->toBe(0)
            ->and($taxByLine['item:uncategorised']->taxMinor)->toBe(2_000)
            ->and($summary->groups)->toHaveCount(2);
    });
});

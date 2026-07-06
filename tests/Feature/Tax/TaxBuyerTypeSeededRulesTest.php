<?php

use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
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
use Database\Seeders\TaxZoneRuleSeeder;

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
 *     euMember: TaxZone,
 *     world: TaxZone,
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass,
 *     taxRate: TaxRate
 * }
 */
function seededRuleTaxInfrastructure(): array
{
    $uk = TaxZone::factory()->uk()->create();
    $euMember = TaxZone::factory()->euMember()->create();
    $world = TaxZone::factory()->world()->create();

    TaxZoneMember::factory()->create(['tax_zone_id' => $uk->id, 'country_code' => 'GB']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $euMember->id, 'country_code' => 'DE']);
    TaxZoneMember::factory()->create(['tax_zone_id' => $world->id, 'country_code' => 'US']);

    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $taxRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $taxRate->id,
        'is_active' => true,
    ]);

    (new TaxZoneRuleSeeder)->run();

    return compact('uk', 'euMember', 'world', 'orgClass', 'productClass', 'taxRate');
}

function summarizeSeededRuleOpportunityTax(Opportunity $opportunity, ProductTaxClass $productClass): DocumentTaxSummary
{
    $context = TaxResolutionContext::fromOpportunity($opportunity->loadMissing(['member', 'store']));

    return app(TaxResolutionContextHolder::class)->using(
        $context,
        fn (): DocumentTaxSummary => app(DocumentTaxBreakdown::class)->summarize(
            new DocumentTaxRequest(
                lines: [
                    new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $productClass->id),
                ],
                currencyCode: $opportunity->currency_code ?: 'GBP',
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $opportunity->starts_at,
            ),
        ),
    );
}

describe('shipped TaxZoneRuleSeeder reachability', function () {
    it('applies the government exempt rule for UK to UK government buyers', function () {
        $zones = seededRuleTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'buyer_type' => TaxBuyerType::Government->value,
            'tax_exempt' => false,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $summary = summarizeSeededRuleOpportunityTax($opportunity->refresh(), $zones['productClass']);

        expect($opportunity->fresh()->tax_total)->toBe(0)
            ->and($summary->groups[0]->resultType)->toBe(TaxResultType::Exempt)
            ->and($summary->groups[0]->legalText)->toContain('Exempt supply');
    });

    it('applies the charity exempt rule for UK to UK charity buyers', function () {
        $zones = seededRuleTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'buyer_type' => TaxBuyerType::Charity->value,
            'tax_exempt' => false,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $summary = summarizeSeededRuleOpportunityTax($opportunity->refresh(), $zones['productClass']);

        expect($opportunity->fresh()->tax_total)->toBe(0)
            ->and($summary->groups[0]->resultType)->toBe(TaxResultType::Exempt);
    });

    it('applies the seeded outside scope rule for UK to world business buyers with a validated tax ID', function () {
        $zones = seededRuleTaxInfrastructure();

        $rule = app(TaxZoneRuleResolver::class)->resolveFromContext(
            $zones['uk']->id,
            $zones['world']->id,
            TaxBuyerType::Business,
            hasTaxId: true,
        );

        expect($rule?->name)->toBe('Outside scope supply')
            ->and($rule?->result_type)->toBe(TaxResultType::OutsideScope)
            ->and($rule?->priority)->toBe(65);
    });

    it('still zero-rates UK export supplies for individual world buyers', function () {
        $zones = seededRuleTaxInfrastructure();

        $rule = app(TaxZoneRuleResolver::class)->resolveFromContext(
            $zones['uk']->id,
            $zones['world']->id,
            TaxBuyerType::Individual,
            hasTaxId: false,
        );

        expect($rule?->name)->toBe('UK export zero rated')
            ->and($rule?->result_type)->toBe(TaxResultType::ZeroRated);
    });

    it('still applies standard UK domestic VAT for business buyers', function () {
        $zones = seededRuleTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'buyer_type' => TaxBuyerType::Business->value,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

        $summary = summarizeSeededRuleOpportunityTax($opportunity->refresh(), $zones['productClass']);

        expect($opportunity->fresh()->tax_total)->toBe(2_000)
            ->and($summary->groups[0]->resultType)->toBe(TaxResultType::Standard);
    });
});

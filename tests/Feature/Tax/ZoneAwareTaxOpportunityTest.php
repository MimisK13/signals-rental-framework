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
 *     productClass2: ProductTaxClass,
 *     taxRate: TaxRate
 * }
 */
function wireZoneTaxInfrastructure(): array
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

function rollupCalculator(): OpportunityTotalsCalculator
{
    return app(OpportunityTotalsCalculator::class);
}

function summarizeOpportunityTax(Opportunity $opportunity, array $lines): DocumentTaxSummary
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

describe('zone-aware opportunity tax totals', function () {
    it('applies standard UK domestic VAT through opportunity rollUp', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'buyer_type' => TaxBuyerType::B2c->value,
        ]);
        $product = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
            'currency_code' => 'GBP',
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe(2_000)
            ->and($opportunity->charge_including_tax_total)->toBe(12_000);

        $summary = summarizeOpportunityTax($opportunity, [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($summary->groups[0]->resultType)->toBe(TaxResultType::Standard)
            ->and($summary->groups[0]->taxTotal)->toBe(2_000);
    });

    it('applies reverse charge with legal text for UK to EU B2B with VAT number', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
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

        rollupCalculator()->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe(0);

        $summary = summarizeOpportunityTax($opportunity, [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($summary->groups[0]->resultType)->toBe(TaxResultType::ReverseCharge)
            ->and($summary->groups[0]->legalText)->toContain('Reverse charge')
            ->and($summary->groups[0]->taxTotal)->toBe(0);
    });

    it('applies standard matrix VAT for UK to EU B2C', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::B2c->value,
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
            'total' => 5_000,
            'unit_price' => 5_000,
        ]);

        rollupCalculator()->rollUp($opportunity->refresh());

        expect($opportunity->fresh()->tax_total)->toBe(1_000);
    });

    it('zero-rates UK export supplies to the world zone', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['world']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
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
            'total' => 8_000,
            'unit_price' => 8_000,
        ]);

        rollupCalculator()->rollUp($opportunity->refresh());

        expect($opportunity->fresh()->tax_total)->toBe(0);

        $summary = summarizeOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 8_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($summary->groups[0]->resultType)->toBe(TaxResultType::ZeroRated)
            ->and($summary->groups[0]->legalText)->toContain('Zero rated');
    });

    it('short-circuits to exempt when the buyer member is tax exempt', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['uk']->id,
            'tax_exempt' => true,
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

        rollupCalculator()->rollUp($opportunity->refresh());

        $summary = summarizeOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($opportunity->fresh()->tax_total)->toBe(0)
            ->and($summary->groups[0]->resultType)->toBe(TaxResultType::Exempt);
    });

    it('marks outside scope supplies distinctly in the tax summary', function () {
        $zones = wireZoneTaxInfrastructure();

        TaxRule::factory()->zoneMatch([
            'name' => 'Outside scope override',
            'seller_zone_id' => $zones['uk']->id,
            'buyer_zone_id' => $zones['world']->id,
            'buyer_type' => TaxBuyerType::B2b,
            'has_tax_id' => true,
            'result_type' => TaxResultType::OutsideScope,
            'legal_text' => 'Outside the scope of UK VAT.',
            'priority' => 200,
        ])->create();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['world']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
            'tax_number' => 'US123456789',
            'tax_number_validated' => true,
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
            'total' => 4_000,
            'unit_price' => 4_000,
        ]);

        rollupCalculator()->rollUp($opportunity->refresh());

        $summary = summarizeOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 4_000, productTaxClassId: $zones['productClass']->id),
        ]);

        expect($opportunity->fresh()->tax_total)->toBe(0)
            ->and($summary->groups[0]->resultType)->toBe(TaxResultType::OutsideScope)
            ->and($summary->groups[0]->legalText)->toContain('Outside the scope');
    });

    it('treats unresolved buyer zones as seller domestic per settings', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id, 'country_code' => 'GB']);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => null,
            'buyer_type' => TaxBuyerType::B2c->value,
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

        rollupCalculator()->rollUp($opportunity->refresh());

        expect($opportunity->fresh()->tax_total)->toBe(2_000);
    });

    it('zero-rates all product classes under a reverse charge rule in a mixed basket', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
            'tax_number' => 'DE999999999',
            'tax_number_validated' => true,
        ]);

        $product1 = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass']->id]);
        $product2 = Product::factory()->sale()->create(['tax_class_id' => $zones['productClass2']->id]);

        $opportunity = Opportunity::factory()->create([
            'member_id' => $member->id,
            'store_id' => $store->id,
        ]);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product1->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 6_000,
            'unit_price' => 6_000,
        ]);
        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $product2->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 4_000,
            'unit_price' => 4_000,
        ]);

        rollupCalculator()->rollUp($opportunity->refresh());

        $summary = summarizeOpportunityTax($opportunity->refresh(), [
            new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 6_000, productTaxClassId: $zones['productClass']->id),
            new DocumentTaxLineInput(lineKey: 'item:2', netMinor: 4_000, productTaxClassId: $zones['productClass2']->id),
        ]);

        expect($opportunity->fresh()->tax_total)->toBe(0)
            ->and($summary->groups)->toHaveCount(2);

        foreach ($summary->groups as $group) {
            expect($group->resultType)->toBe(TaxResultType::ReverseCharge)
                ->and($group->taxTotal)->toBe(0);
        }
    });

    it('produces replay-stable tax totals across repeated rollUps', function () {
        $zones = wireZoneTaxInfrastructure();

        $store = Store::factory()->create(['tax_zone_id' => $zones['uk']->id]);
        $member = Member::factory()->organisation()->create([
            'sale_tax_class_id' => $zones['orgClass']->id,
            'tax_zone_id' => $zones['euMember']->id,
            'buyer_type' => TaxBuyerType::B2b->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
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

        rollupCalculator()->rollUp($opportunity->refresh());
        $firstTax = $opportunity->fresh()->tax_total;

        rollupCalculator()->rollUp($opportunity->refresh());
        $secondTax = $opportunity->fresh()->tax_total;

        rollupCalculator()->rollUp($opportunity->refresh());

        expect($firstTax)->toBe(0)
            ->and($secondTax)->toBe($firstTax)
            ->and($opportunity->fresh()->tax_total)->toBe($firstTax);
    });
});

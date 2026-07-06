<?php

use App\Enums\PricingRoundingDirection;
use App\Enums\TaxPointPolicy;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OrganisationTaxClass;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Services\Tax\TaxPointDateResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
    settings()->set('finance.tax_point_policy', TaxPointPolicy::SupplyDate->value);
});

/**
 * @return array{
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass,
 *     product: Product,
 *     store: Store,
 *     member: Member
 * }
 */
function wireDatedClassMapTaxRules(): array
{
    $orgClass = OrganisationTaxClass::factory()->create();
    $productClass = ProductTaxClass::factory()->create(['is_default' => true]);
    $lowRate = TaxRate::factory()->create(['name' => 'Reduced VAT', 'rate' => '5.0000', 'is_active' => true]);
    $highRate = TaxRate::factory()->create(['name' => 'Standard VAT', 'rate' => '20.0000', 'is_active' => true]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $lowRate->id,
        'effective_to' => '2026-06-01',
        'priority' => 10,
        'is_active' => true,
    ]);

    TaxRule::factory()->create([
        'organisation_tax_class_id' => $orgClass->id,
        'product_tax_class_id' => $productClass->id,
        'tax_rate_id' => $highRate->id,
        'effective_from' => '2026-06-02',
        'priority' => 10,
        'is_active' => true,
    ]);

    $store = Store::factory()->create();
    $member = Member::factory()->organisation()->create(['sale_tax_class_id' => $orgClass->id]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $productClass->id]);

    return compact('orgClass', 'productClass', 'product', 'store', 'member');
}

function datedTaxOpportunity(Member $member, Store $store): Opportunity
{
    return Opportunity::factory()->create([
        'member_id' => $member->id,
        'store_id' => $store->id,
        'currency_code' => 'GBP',
        'starts_at' => Carbon::parse('2026-05-15T09:00:00Z'),
        'deliver_starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
    ]);
}

describe('TaxPointDateResolver', function () {
    it('prefers deliver_starts_at when supply_date policy is configured', function () {
        settings()->set('finance.tax_point_policy', TaxPointPolicy::SupplyDate->value);

        $opportunity = Opportunity::factory()->create([
            'starts_at' => Carbon::parse('2026-05-15T09:00:00Z'),
            'deliver_starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        ]);

        $resolved = app(TaxPointDateResolver::class)->resolveForOpportunity($opportunity);

        expect($resolved?->format('Y-m-d'))->toBe('2026-07-01');
    });

    it('uses starts_at when invoice_date policy is configured', function () {
        settings()->set('finance.tax_point_policy', TaxPointPolicy::InvoiceDate->value);

        $opportunity = Opportunity::factory()->create([
            'starts_at' => Carbon::parse('2026-05-15T09:00:00Z'),
            'deliver_starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        ]);

        $resolved = app(TaxPointDateResolver::class)->resolveForOpportunity($opportunity);

        expect($resolved?->format('Y-m-d'))->toBe('2026-05-15');
    });
});

describe('tax-point rate selection on opportunity rollUp', function () {
    it('selects the rate effective on the supply date by default', function () {
        $infra = wireDatedClassMapTaxRules();
        $opportunity = datedTaxOpportunity($infra['member'], $infra['store']);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $infra['product']->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe(2_000);
    });

    it('selects the rate effective on the order date when invoice_date policy is set', function () {
        settings()->set('finance.tax_point_policy', TaxPointPolicy::InvoiceDate->value);

        $infra = wireDatedClassMapTaxRules();
        $opportunity = datedTaxOpportunity($infra['member'], $infra['store']);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $infra['product']->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        $opportunity->refresh();

        expect($opportunity->tax_total)->toBe(500);
    });

    it('reads finance.tax_point_policy at fire-time on each rollUp', function () {
        $infra = wireDatedClassMapTaxRules();
        $opportunity = datedTaxOpportunity($infra['member'], $infra['store']);

        OpportunityItem::factory()->sale()->create([
            'opportunity_id' => $opportunity->id,
            'itemable_id' => $infra['product']->id,
            'itemable_type' => Product::class,
            'quantity' => '1',
            'total' => 10_000,
            'unit_price' => 10_000,
        ]);

        $calculator = app(OpportunityTotalsCalculator::class);

        $calculator->rollUp($opportunity->refresh());
        expect($opportunity->refresh()->tax_total)->toBe(2_000);

        settings()->set('finance.tax_point_policy', TaxPointPolicy::InvoiceDate->value);

        $calculator->rollUp($opportunity->refresh());
        expect($opportunity->refresh()->tax_total)->toBe(500);
    });
});

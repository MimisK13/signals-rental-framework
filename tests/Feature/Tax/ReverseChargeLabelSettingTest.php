<?php

use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
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
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\ValueObjects\Tax\DocumentTaxSummary;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{
 *     uk: TaxZone,
 *     euMember: TaxZone,
 *     world: TaxZone,
 *     orgClass: OrganisationTaxClass,
 *     productClass: ProductTaxClass
 * }
 */
function reverseChargeLabelFixture(): array
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

    return compact('uk', 'euMember', 'world', 'orgClass', 'productClass');
}

function summarizeReverseChargeTax(Opportunity $opportunity, int $productTaxClassId): DocumentTaxSummary
{
    $context = TaxResolutionContext::fromOpportunity($opportunity->loadMissing(['member', 'store']));

    return app(TaxResolutionContextHolder::class)->using(
        $context,
        fn (): DocumentTaxSummary => app(DocumentTaxBreakdown::class)->summarize(
            new DocumentTaxRequest(
                lines: [
                    new DocumentTaxLineInput(lineKey: 'item:1', netMinor: 10_000, productTaxClassId: $productTaxClassId),
                ],
                currencyCode: $opportunity->currency_code ?: 'GBP',
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $opportunity->starts_at,
            ),
        ),
    );
}

it('defaults reverse_charge_label to Reverse charge', function () {
    expect(settings('finance.reverse_charge_label'))->toBe('Reverse charge');
});

it('uses the configured reverse charge label in tax breakdown results', function () {
    settings()->set('finance.reverse_charge_label', 'Customer VAT due');

    $fixture = reverseChargeLabelFixture();

    $store = Store::factory()->create(['tax_zone_id' => $fixture['uk']->id]);
    $member = Member::factory()->organisation()->create([
        'sale_tax_class_id' => $fixture['orgClass']->id,
        'tax_zone_id' => $fixture['euMember']->id,
        'buyer_type' => TaxBuyerType::Business->value,
        'tax_number' => 'DE123456789',
        'tax_number_validated' => true,
        'tax_number_validated_at' => Carbon::now(),
    ]);
    $product = Product::factory()->sale()->create(['tax_class_id' => $fixture['productClass']->id]);

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

    $summary = summarizeReverseChargeTax($opportunity, $fixture['productClass']->id);

    expect($summary->groups[0]->resultType)->toBe(TaxResultType::ReverseCharge)
        ->and($summary->groups[0]->taxRateName)->toBe('Customer VAT due');
});

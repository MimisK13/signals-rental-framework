<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Products\CreateProduct;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Products\CreateProductData;
use App\Enums\PricingRoundingDirection;
use App\Enums\ProductType;
use App\Enums\TaxBuyerType;
use App\Enums\TaxResultType;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Models\Store;
use App\Models\TaxCategory;
use App\Models\TaxZone;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TaxCategorySeeder;
use Database\Seeders\TaxClassSeeder;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TaxRuleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Database\Seeders\TaxZoneSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->seed(CountrySeeder::class);
    $this->seed(StoreSeeder::class);
    $this->seed(TaxClassSeeder::class);
    $this->seed(TaxZoneSeeder::class);
    $this->seed(TaxCategorySeeder::class);
    $this->seed(TaxRateSeeder::class);
    $this->seed(TaxRuleSeeder::class);
    $this->seed(TaxZoneRuleSeeder::class);
    $this->seed(ProductGroupSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->actingAs(User::factory()->owner()->create());

    settings()->set('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);
    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
});

/**
 * @return array{store: Store, member: Member}
 */
function freshInstallUkSaleContext(): array
{
    $store = Store::query()->where('is_default', true)->firstOrFail();
    $member = Member::factory()->organisation()->create([
        'tax_zone_id' => $store->tax_zone_id,
        'buyer_type' => TaxBuyerType::Business->value,
    ]);

    return compact('store', 'member');
}

function freshInstallRollup(Opportunity $opportunity): Opportunity
{
    app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());

    return $opportunity->refresh();
}

function freshInstallOrderOpportunity(Opportunity $opportunity): Opportunity
{
    (new ConvertToQuotation)($opportunity->fresh());
    (new ConvertToOrder)($opportunity->fresh());

    return $opportunity->fresh();
}

describe('fresh install tax defaults', function () {
    it('seeds the default store with a UK seller tax zone', function () {
        $store = Store::query()->where('is_default', true)->firstOrFail();

        expect($store->country_code)->toBe('GB')
            ->and($store->tax_zone_id)->not->toBeNull();
    });

    it('seeds a configurable default product tax class setting', function () {
        $defaultClass = ProductTaxClass::query()->where('is_default', true)->firstOrFail();

        expect((int) settings('tax.default_product_tax_class_id'))->toBe($defaultClass->id);
    });

    it('defaults new products to the standard product tax class', function () {
        $defaultClass = ProductTaxClass::query()->where('is_default', true)->firstOrFail();

        $product = (new CreateProduct)(CreateProductData::from([
            'name' => 'Fresh Install Product',
            'product_type' => ProductType::Rental->value,
        ]));

        expect($product->tax_class_id)->toBe($defaultClass->id);
    });

    it('computes penny-exact 20% VAT on UK domestic rental lines without explicit tax classes', function () {
        ['store' => $store, 'member' => $member] = freshInstallUkSaleContext();

        $product = Product::factory()->rental()->create([
            'tax_class_id' => null,
        ]);

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

        $opportunity = freshInstallRollup($opportunity);

        expect($opportunity->tax_total)->toBe(2_000)
            ->and($opportunity->charge_including_tax_total)->toBe(12_000);
    });

    it('computes 5% VAT for products with the reduced sale tax category', function () {
        ['store' => $store, 'member' => $member] = freshInstallUkSaleContext();

        $reducedCategory = TaxCategory::query()->where('slug', 'reduced')->firstOrFail();

        $product = Product::factory()->sale()->create([
            'tax_class_id' => null,
            'sale_tax_category_id' => $reducedCategory->id,
        ]);

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

        $opportunity = freshInstallRollup($opportunity);

        expect($opportunity->tax_total)->toBe(500);
    });

    it('keeps reverse charge at zero with legal text for validated EU B2B buyers', function () {
        $store = Store::query()->where('is_default', true)->firstOrFail();
        $euZoneId = TaxZone::query()->where('slug', 'eu_member')->value('id');

        $member = Member::factory()->organisation()->create([
            'tax_zone_id' => $euZoneId,
            'buyer_type' => TaxBuyerType::Business->value,
            'tax_number' => 'DE123456789',
            'tax_number_validated' => true,
            'tax_number_validated_at' => now(),
        ]);

        $product = Product::factory()->rental()->create(['tax_class_id' => null]);

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

        $opportunity = freshInstallRollup($opportunity);

        expect($opportunity->tax_total)->toBe(0);

        $opportunity = freshInstallOrderOpportunity($opportunity);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
            'reference' => 'FRESH-RC-001',
        ]));

        $invoiceRow = Invoice::query()->with(['taxes', 'items'])->findOrFail($invoice->id);

        expect($invoiceRow->tax_total)->toBe(0)
            ->and($invoiceRow->items->first()->tax_treatment)->toBe(TaxResultType::ReverseCharge->value);
    });

    it('keeps tax-exempt members at zero tax', function () {
        ['store' => $store] = freshInstallUkSaleContext();

        $member = Member::factory()->organisation()->create([
            'tax_zone_id' => $store->tax_zone_id,
            'tax_exempt' => true,
        ]);

        $product = Product::factory()->rental()->create(['tax_class_id' => null]);

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

        $opportunity = freshInstallRollup($opportunity);

        expect($opportunity->tax_total)->toBe(0);
    });

    it('carries standard VAT through to invoices generated from UK opportunities', function () {
        ['store' => $store, 'member' => $member] = freshInstallUkSaleContext();

        $product = Product::factory()->rental()->create(['tax_class_id' => null]);

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

        $opportunity = freshInstallRollup($opportunity);
        $opportunity = freshInstallOrderOpportunity($opportunity);

        $invoice = (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
            'opportunity_id' => $opportunity->id,
            'reference' => 'FRESH-INV-001',
        ]));

        $invoiceRow = Invoice::query()->with('taxes')->findOrFail($invoice->id);

        expect($invoiceRow->tax_total)->toBe(2_000)
            ->and($invoiceRow->taxes->contains(fn ($tax) => $tax->rate_percentage === '20.0000'))->toBeTrue();
    });
});

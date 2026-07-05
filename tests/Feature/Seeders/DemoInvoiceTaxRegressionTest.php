<?php

use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\User;
use App\Services\Invoices\InvoiceTotalsCalculator;
use App\Services\Invoices\OpportunityInvoiceSnapshotter;
use Carbon\CarbonImmutable;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DiscountCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PriceCategorySeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RateDefinitionPresetSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\TaxCategorySeeder;
use Database\Seeders\TaxClassSeeder;
use Database\Seeders\TaxRateSeeder;
use Database\Seeders\TaxRuleSeeder;
use Database\Seeders\TaxZoneRuleSeeder;
use Database\Seeders\TaxZoneSeeder;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    Queue::fake();

    settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');

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
    $this->seed(DiscountCategorySeeder::class);
    $this->seed(PriceCategorySeeder::class);
    $this->seed(RateDefinitionPresetSeeder::class);

    User::factory()->owner()->create(['email' => 'test@example.com']);

    Member::factory()
        ->organisation()
        ->count(25)
        ->create(['tag_list' => ['demo-data']]);
});

function seedFreshInstallDemoPhase4(): void
{
    seedDemoMemberEnrichment();
    seedDemoProducts();
    seedDemoProductEnrichment();
    seedDemoOpportunities();
}

function assertDemoInvoiceTotalMatchesOpportunity(Opportunity $opportunity): void
{
    $snapshot = app(OpportunityInvoiceSnapshotter::class)->snapshot($opportunity->fresh());
    $invoiceTotals = app(InvoiceTotalsCalculator::class)->calculate(
        $opportunity->fresh(),
        $snapshot['lines'],
        $snapshot['tax_lines'],
        $snapshot['net_headline'],
        CarbonImmutable::now('UTC'),
    );

    expect($invoiceTotals['total'])->toBe((int) $opportunity->charge_including_tax_total);
}

it('generates a standard invoice from a seeded demo order without penny-invariant mismatch', function () {
    seedFreshInstallDemoPhase4();

    $opportunity = Opportunity::query()->where('reference', 'DEMO-OPP-0001')->firstOrFail();

    expect($opportunity->tax_total)->toBeGreaterThan(0)
        ->and($opportunity->tax_locked)->toBeTrue();

    assertDemoInvoiceTotalMatchesOpportunity($opportunity);

    $owner = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->actingAs($owner);

    (new GenerateInvoiceFromOpportunity)(GenerateInvoiceFromOpportunityData::from([
        'opportunity_id' => $opportunity->id,
        'reference' => 'REGRESSION-INV-0001',
    ]));
});

it('keeps invoice totals aligned for every seeded demo order opportunity', function () {
    seedFreshInstallDemoPhase4();

    $orderReferences = [
        'DEMO-OPP-0001',
        'DEMO-OPP-0002',
        'DEMO-OPP-0008',
        'DEMO-OPP-0009',
        'DEMO-OPP-0010',
        'DEMO-OPP-0011',
        'DEMO-OPP-0012',
        'DEMO-OPP-0013',
    ];

    foreach ($orderReferences as $reference) {
        $opportunity = Opportunity::query()->where('reference', $reference)->firstOrFail();

        assertDemoInvoiceTotalMatchesOpportunity($opportunity);
    }
});

it('seeds demo invoices through the seeder path without penny-invariant mismatch', function () {
    seedFreshInstallDemoPhase4();
    seedDemoInvoices();

    $paid = Invoice::query()->where('reference', 'DEMO-INV-PAID')->firstOrFail();
    $opportunity = Opportunity::query()->where('reference', 'DEMO-OPP-0002')->firstOrFail();

    expect($paid->total)->toBe((int) $opportunity->charge_including_tax_total)
        ->and($paid->tax_total)->toBeGreaterThan(0);

    $deposit = Invoice::query()->where('reference', 'DEMO-INV-DEPOSIT')->firstOrFail();
    $proforma = Invoice::query()->where('reference', 'DEMO-INV-PROFORMA')->firstOrFail();

    expect($deposit->number)->toStartWith('DEP-')
        ->and($proforma->number)->toStartWith('PRO-');
});

it('defers reduced sale tax category assignment until after demo invoices are seeded', function () {
    seedFreshInstallDemoPhase4();

    $gaffer = Product::query()->where('sku', 'DEMO-CON-GAF50')->firstOrFail();

    expect($gaffer->sale_tax_category_id)->toBeNull();

    seedDemoInvoices();
    applyDeferredDemoProductTaxCategories();

    expect($gaffer->refresh()->sale_tax_category_id)->not->toBeNull();
});

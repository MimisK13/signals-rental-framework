<?php

use App\Domain\Administration\Models\User;
use App\Domain\Assets\Enums\AssetOwnershipType;
use App\Domain\Assets\Models\Asset;
use App\Domain\Assets\Models\AssetUsage;
use App\Domain\Assets\Presentation\Livewire\ProductFinancials;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Models\InvoiceItem;
use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Stock\Models\StockLevel;
use App\Support\Formatter;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

/**
 * @return array{asset: Asset, item: OpportunityItem}
 */
function makeProductFinancialsUiAsset(Product $product, int $acquisition = 10000, int $revenue = 0): array
{
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'ownership_type' => AssetOwnershipType::CompanyOwned,
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => $acquisition,
    ]);

    StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'asset_id' => $asset->id,
        'asset_number' => 'M32-'.$asset->id,
    ]);

    if ($revenue === 0) {
        return ['asset' => $asset->refresh(), 'item' => new OpportunityItem];
    }

    $item = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => 1,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'currency_code' => 'GBP',
        'subtotal' => $revenue,
        'total' => $revenue,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total' => $revenue,
        'currency_code' => 'GBP',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-02 10:00:00',
    ]);

    return ['asset' => $asset->refresh(), 'item' => $item];
}

it('renders product fleet financials and links the asset breakdown', function (): void {
    $product = Product::factory()->rental()->serialised()->create(['name' => 'Midas M32']);
    $first = makeProductFinancialsUiAsset($product, acquisition: 20000, revenue: 10000);
    makeProductFinancialsUiAsset($product, acquisition: 30000, revenue: 60000);
    $formatter = app(Formatter::class);

    $response = $this->get(route('products.financials', $product));

    $response->assertOk();

    Livewire::test(ProductFinancials::class, ['product' => $product])
        ->assertSee('Fleet')
        ->assertSee('Owned Assets')
        ->assertSee('2')
        ->assertSee('Rental Revenue')
        ->assertSee('Investment ROI')
        ->assertSee('Asset Breakdown')
        ->assertSee($formatter->money(70000, 'GBP'))
        ->assertSee($formatter->money(50000, 'GBP'))
        ->assertSee('140.00%')
        ->assertSee($formatter->money(20000, 'GBP'))
        ->assertSee('40.00%')
        ->assertSee('M32-'.$first['asset']->id)
        ->assertSee(route('assets.show', $first['asset']), false);
});

it('preserves incomplete fleet financials while showing confirmed revenue', function (): void {
    $product = Product::factory()->rental()->serialised()->create(['name' => 'Midas M32']);
    makeProductFinancialsUiAsset($product, acquisition: 10000, revenue: 20000);
    $ambiguous = makeProductFinancialsUiAsset($product, acquisition: 10000);
    $helper = Asset::factory()->create(['product_id' => $product->id]);
    $item = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => 1,
    ]);
    $invoice = Invoice::factory()->issued()->create(['currency_code' => 'GBP', 'subtotal' => 9000, 'total' => 9000]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total' => 9000,
        'currency_code' => 'GBP',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $ambiguous['asset']->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-01 12:00:00',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $helper->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 12:00:00',
        'returned_at' => '2026-10-02 10:00:00',
    ]);

    $rendered = Livewire::test(ProductFinancials::class, ['product' => $product])->html();

    expect($rendered)
        ->toContain('Financial history incomplete')
        ->toContain('Some Asset rental revenue cannot be reliably attributed.')
        ->toContain(app(Formatter::class)->money(20000, 'GBP'))
        ->toContain('Investment ROI')
        ->toContain("\u{2014}");
});

it('preserves zero revenue and valid zero recovery for an unused asset', function (): void {
    $product = Product::factory()->rental()->serialised()->create(['name' => 'Midas M32']);
    makeProductFinancialsUiAsset($product, acquisition: 320000);

    Livewire::test(ProductFinancials::class, ['product' => $product])
        ->assertSee('0.00%')
        ->assertSee(app(Formatter::class)->money(0, 'GBP').' of '.app(Formatter::class)->money(320000, 'GBP').' recovered')
        ->assertSee(app(Formatter::class)->money(320000, 'GBP').' remaining to break-even')
        ->assertDontSee('Financial history incomplete');
});

it('forbids users without stock access', function (): void {
    $product = Product::factory()->rental()->serialised()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('products.financials', $product))->assertForbidden();
});

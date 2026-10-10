<?php

use App\Enums\LineItemTransactionType;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\Formatter;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

/**
 * @return array{asset: Asset, item: OpportunityItem}
 */
function createAssetUiRevenueFixture(int $amount = 20000, int $acquisition = 10000): array
{
    $product = Product::factory()->rental()->serialised()->create(['name' => 'Midas M32']);
    $item = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => 1,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'currency_code' => 'GBP',
        'subtotal' => $amount,
        'total' => $amount,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total' => $amount,
        'currency_code' => 'GBP',
    ]);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => $acquisition,
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-02 10:00:00',
    ]);

    return compact('asset', 'item');
}

it('displays the asset product and physical identifiers', function (): void {
    $product = Product::factory()->serialised()->create(['name' => 'Midas M32']);
    $asset = Asset::factory()->create(['product_id' => $product->id]);
    StockLevel::factory()->create([
        'asset_id' => $asset->id,
        'product_id' => $product->id,
        'asset_number' => 'M32-003',
        'serial_number' => '01293482',
        'barcode' => 'BAR-003',
    ]);

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('Midas M32')
        ->assertSee('M32-003')
        ->assertSee('01293482')
        ->assertSee('BAR-003')
        ->assertDontSee('Asset #');
});

it('displays acquisition and financial metrics without duplicating calculations', function (): void {
    $product = Product::factory()->rental()->serialised()->create(['name' => 'Midas M32']);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 100000,
        'acquired_at' => '2026-01-10',
    ]);
    AssetExpense::factory()->create([
        'asset_id' => $asset->id,
        'base_currency_code' => 'GBP',
        'base_amount' => 10000,
    ]);

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('Acquisition Cost')
        ->assertSee('Investment ROI')
        ->assertSee('Cost Recovery')
        ->assertSee('Financials')
        ->assertSee('Rental Revenue')
        ->assertSee('Completed Rentals')
        ->assertSee('Rental Days')
        ->assertDontSee('Confirmed Rental Revenue')
        ->assertDontSee('Recovered / Acquisition')
        ->assertSee(app(Formatter::class)->money(100000, 'GBP'));
});

it('keeps valid zero money separate from unavailable financial values', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('Rental Revenue')
        ->assertSee(app(Formatter::class)->money(0, 'GBP'))
        ->assertSee('Acquisition Cost')
        ->assertSee('—');
});

it('displays zero recovery as a valid financial value', function (): void {
    $asset = Asset::factory()->create([
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 100000,
    ]);
    $formatter = app(Formatter::class);

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('0.00%')
        ->assertSee($formatter->money(0, 'GBP').' of '.$formatter->money(100000, 'GBP').' recovered')
        ->assertSee($formatter->money(100000, 'GBP').' remaining to break-even')
        ->assertDontSee('Acquisition cost fully recovered');
});

it('displays the real cost recovery percentage while clamping only the progress bar', function (): void {
    $fixture = createAssetUiRevenueFixture();
    $formatter = app(Formatter::class);

    Volt::test('assets.show', ['asset' => $fixture['asset']])
        ->assertSee('200.00%')
        ->assertSee('style="width: 100%;"', false)
        ->assertSee('Acquisition cost fully recovered')
        ->assertSee($formatter->money(10000, 'GBP').' above break-even')
        ->assertDontSee($formatter->money(20000, 'GBP').' of '.$formatter->money(10000, 'GBP').' recovered');
});

it('keeps the partial recovery explanation below break-even', function (): void {
    $fixture = createAssetUiRevenueFixture(amount: 4000, acquisition: 10000);
    $formatter = app(Formatter::class);

    Volt::test('assets.show', ['asset' => $fixture['asset']])
        ->assertSee('40.00%')
        ->assertSee($formatter->money(4000, 'GBP').' of '.$formatter->money(10000, 'GBP').' recovered')
        ->assertSee($formatter->money(6000, 'GBP').' remaining to break-even')
        ->assertDontSee('Acquisition cost fully recovered');
});

it('uses the full recovery explanation at exactly break-even', function (): void {
    $fixture = createAssetUiRevenueFixture(amount: 10000, acquisition: 10000);
    $formatter = app(Formatter::class);

    Volt::test('assets.show', ['asset' => $fixture['asset']])
        ->assertSee('100.00%')
        ->assertSee('Acquisition cost fully recovered')
        ->assertSee($formatter->money(0, 'GBP').' above break-even')
        ->assertDontSee($formatter->money(10000, 'GBP').' of '.$formatter->money(10000, 'GBP').' recovered');
});

it('preserves the incomplete revenue warning and unavailable metrics', function (): void {
    $fixture = createAssetUiRevenueFixture(amount: 40000, acquisition: 10000);
    $secondAsset = Asset::factory()->create([
        'product_id' => $fixture['asset']->product_id,
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 10000,
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $secondAsset->id,
        'opportunity_item_id' => $fixture['item']->id,
        'dispatched_at' => '2026-10-01 14:00:00',
    ]);

    Volt::test('assets.show', ['asset' => $fixture['asset']])
        ->assertSee('Financial history incomplete')
        ->assertSee('cannot be reliably attributed')
        ->assertDontSee('Acquisition cost fully recovered')
        ->assertSee('Investment ROI')
        ->assertSee('—');
});

it('displays actual usage history and active usage', function (): void {
    $asset = Asset::factory()->create();
    $item = OpportunityItem::factory()->create();
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => null,
    ]);

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('Actual Usage History')
        ->assertSee('Active')
        ->assertSee('History');
});

it('shows an empty state when the asset has no usage history', function (): void {
    $asset = Asset::factory()->create();

    Volt::test('assets.show', ['asset' => $asset])
        ->assertSee('No usage history')
        ->assertSee('has not been recorded as physically dispatched');
});

it('does not expose the asset detail without stock access', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('assets.show', Asset::factory()->create()));

    $response->assertForbidden();
});

it('returns not found for a missing asset', function (): void {
    $response = $this->get(route('assets.show', 999999999));

    $response->assertNotFound();
});

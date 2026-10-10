<?php

use App\Enums\AssetRentalRevenueAttributionStatus;
use App\Enums\LineItemTransactionType;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Assets\AssetFinancialsCalculator;
use App\Services\Assets\AssetFinancialsResult;
use Illuminate\Support\Carbon;

/**
 * @return array{asset: Asset, item: OpportunityItem, invoiceItem: InvoiceItem}
 */
function createFinancialsFixture(
    int $amount = 10000,
    string $currency = 'GBP',
    int $acquisition = 100000,
    int $quantity = 1,
): array {
    $product = Product::factory()->rental()->serialised()->create();
    $item = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => $quantity,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'currency_code' => $currency,
        'subtotal' => $amount,
        'total' => $amount,
    ]);
    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'total' => $amount,
        'currency_code' => $currency,
    ]);
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'base_currency_code' => $currency,
        'base_acquisition_amount' => $acquisition,
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-04 10:00:00',
    ]);

    return compact('asset', 'item', 'invoiceItem');
}

function calculatorForAsset(Asset $asset, ?Carbon $asOf = null): AssetFinancialsResult
{
    return app(AssetFinancialsCalculator::class)->calculate($asset, $asOf);
}

it('calculates rental revenue and net rental income from base expenses', function (): void {
    $fixture = createFinancialsFixture(amount: 440000, acquisition: 628144);
    AssetExpense::factory()->create([
        'asset_id' => $fixture['asset']->id,
        'base_currency_code' => 'GBP',
        'base_amount' => 32000,
    ]);

    $result = calculatorForAsset($fixture['asset']);

    expect($result->confirmedAttributedRentalRevenue)->toBe(440000)
        ->and($result->operatingExpenses)->toBe(32000)
        ->and($result->netRentalIncome)->toBe(408000)
        ->and($result->costRecoveryRatio)->toBe('0.6495325913')
        ->and($result->remainingToBreakEven)->toBe(220144)
        ->and($result->investmentProfit)->toBe(-220144)
        ->and($result->investmentRoi)->toBe('-0.3504674087');
});

it('treats an asset with no rental revenue as a valid zero', function (): void {
    $asset = Asset::factory()->create([
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 100000,
    ]);

    $result = calculatorForAsset($asset);

    expect($result->revenueStatus)->toBe(AssetRentalRevenueAttributionStatus::NotApplicable)
        ->and($result->confirmedAttributedRentalRevenue)->toBe(0)
        ->and($result->operatingExpenses)->toBe(0)
        ->and($result->netRentalIncome)->toBe(0)
        ->and($result->investmentRoi)->toBe('-1.0000000000');
});

it('propagates ambiguous revenue without calculating financial metrics', function (): void {
    $fixture = createFinancialsFixture(amount: 40000, acquisition: 100000);
    $secondAsset = Asset::factory()->create([
        'product_id' => $fixture['asset']->product_id,
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 100000,
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $secondAsset->id,
        'opportunity_item_id' => $fixture['item']->id,
        'dispatched_at' => '2026-10-01 14:00:00',
    ]);

    $result = calculatorForAsset($fixture['asset']);

    expect($result->revenueStatus)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous)
        ->and($result->revenueComplete)->toBeFalse()
        ->and($result->confirmedAttributedRentalRevenue)->toBe(0)
        ->and($result->netRentalIncome)->toBeNull()
        ->and($result->investmentRoi)->toBeNull()
        ->and($result->unavailableReasons)->toContain('revenue_ambiguous');
});

it('sums multiple expenses and uses normalized amounts', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    AssetExpense::factory()->create(['asset_id' => $asset->id, 'base_amount' => 1250]);
    AssetExpense::factory()->create(['asset_id' => $asset->id, 'base_amount' => 2750]);

    $result = calculatorForAsset($asset);

    expect($result->operatingExpenses)->toBe(4000);
});

it('marks expenses incomplete instead of ignoring missing normalized data', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    AssetExpense::factory()->create([
        'asset_id' => $asset->id,
        'base_currency_code' => '',
        'base_amount' => 1000,
    ]);

    $result = calculatorForAsset($asset);

    expect($result->operatingExpenses)->toBeNull()
        ->and($result->netRentalIncome)->toBeNull()
        ->and($result->unavailableReasons)->toContain('expense_currency_unavailable');
});

it('does not combine incompatible revenue currency with base amounts', function (): void {
    $fixture = createFinancialsFixture(currency: 'EUR', acquisition: 100000);
    $fixture['asset']->update(['base_currency_code' => 'GBP']);

    $result = calculatorForAsset($fixture['asset']);

    expect($result->confirmedAttributedRentalRevenue)->toBe(0)
        ->and($result->revenueComplete)->toBeFalse()
        ->and($result->netRentalIncome)->toBeNull()
        ->and($result->unavailableReasons)->toContain('revenue_currency_unavailable');
});

it('makes acquisition-dependent metrics unavailable without acquisition data', function (): void {
    $fixture = createFinancialsFixture(acquisition: 0);
    $fixture['asset']->update(['base_acquisition_amount' => null]);

    $result = calculatorForAsset($fixture['asset']->fresh());

    expect($result->netRentalIncome)->toBe(10000)
        ->and($result->costRecoveryRatio)->toBeNull()
        ->and($result->remainingToBreakEven)->toBeNull()
        ->and($result->investmentProfit)->toBeNull()
        ->and($result->investmentRoi)->toBeNull()
        ->and($result->unavailableReasons)->toContain('acquisition_unknown');
});

it('calculates zero-income recovery before break-even', function (): void {
    $asset = Asset::factory()->create([
        'base_currency_code' => 'GBP',
        'base_acquisition_amount' => 10000,
    ]);

    $result = calculatorForAsset($asset);

    expect($result->costRecoveryRatio)->toBe('0.0000000000')
        ->and($result->remainingToBreakEven)->toBe(10000)
        ->and($result->investmentProfit)->toBe(-10000)
        ->and($result->investmentRoi)->toBe('-1.0000000000');
});

it('calculates exact and above break-even without capping recovery', function (): void {
    $atBreakEven = createFinancialsFixture(amount: 10000, acquisition: 10000);
    $aboveBreakEven = createFinancialsFixture(amount: 20000, acquisition: 10000);

    $breakEven = calculatorForAsset($atBreakEven['asset']);
    $above = calculatorForAsset($aboveBreakEven['asset']);

    expect($breakEven->costRecoveryRatio)->toBe('1.0000000000')
        ->and($breakEven->remainingToBreakEven)->toBe(0)
        ->and($breakEven->investmentProfit)->toBe(0)
        ->and($breakEven->investmentRoi)->toBe('0.0000000000')
        ->and($above->costRecoveryRatio)->toBe('2.0000000000')
        ->and($above->remainingToBreakEven)->toBe(0)
        ->and($above->investmentProfit)->toBe(10000)
        ->and($above->investmentRoi)->toBe('1.0000000000');
});

it('counts all and completed physical rental participations', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    $firstItem = OpportunityItem::factory()->create();
    $secondItem = OpportunityItem::factory()->create();
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $firstItem->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-04 10:00:00',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $secondItem->id,
        'dispatched_at' => '2026-10-05 10:00:00',
    ]);

    $result = calculatorForAsset($asset, Carbon::parse('2026-10-07 10:00:00'));

    expect($result->rentalCount)->toBe(2)
        ->and($result->completedRentalCount)->toBe(1)
        ->and($result->actualRentalDays)->toBe(5);
});

it('counts sequential substitutions as separate rentals for the asset being measured', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    $firstItem = OpportunityItem::factory()->create();
    $secondItem = OpportunityItem::factory()->create();
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $firstItem->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-01 14:00:00',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $secondItem->id,
        'dispatched_at' => '2026-10-02 10:00:00',
        'returned_at' => '2026-10-03 10:00:00',
    ]);

    $result = calculatorForAsset($asset);

    expect($result->rentalCount)->toBe(2)
        ->and($result->completedRentalCount)->toBe(2)
        ->and($result->actualRentalDays)->toBe(2);
});

it('uses the supplied reporting boundary for active usage', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    $item = OpportunityItem::factory()->create();
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 10:00:00',
    ]);

    $result = calculatorForAsset($asset, Carbon::parse('2026-10-03 10:00:00'));

    expect($result->actualRentalDays)->toBe(2);
});

it('marks invalid usage duration incomplete', function (): void {
    $asset = Asset::factory()->create(['base_currency_code' => 'GBP']);
    $item = OpportunityItem::factory()->create();
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-04 10:00:00',
        'returned_at' => '2026-10-01 10:00:00',
    ]);

    $result = calculatorForAsset($asset);

    expect($result->actualRentalDays)->toBeNull()
        ->and($result->unavailableReasons)->toContain('usage_incomplete');
});

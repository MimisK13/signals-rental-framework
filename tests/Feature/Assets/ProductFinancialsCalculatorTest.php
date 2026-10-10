<?php

use App\Enums\AssetOwnershipType;
use App\Enums\LineItemTransactionType;
use App\Enums\StockCategory;
use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Assets\ProductFinancialsCalculator;

/**
 * @param  array<string, mixed>  $assetOverrides
 * @param  array<string, mixed>  $stockOverrides
 * @return array{asset: Asset, stock: StockLevel}
 */
function makeProductFinancialAsset(Product $product, array $assetOverrides = [], array $stockOverrides = []): array
{
    $asset = Asset::factory()->create([
        'product_id' => $product->id,
        'ownership_type' => AssetOwnershipType::CompanyOwned,
        'base_currency_code' => 'GBP',
        ...$assetOverrides,
    ]);
    $stock = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'asset_id' => $asset->id,
        ...$stockOverrides,
    ]);

    return ['asset' => $asset->refresh(), 'stock' => $stock];
}

function addProductRentalRevenue(Asset $asset, int $amount, string $date = '2026-10-01 10:00:00'): OpportunityItem
{
    $item = OpportunityItem::factory()->create([
        'itemable_id' => $asset->product_id,
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
        'product_id' => $asset->product_id,
        'quantity' => 1,
        'total' => $amount,
        'currency_code' => 'GBP',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $asset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => $date,
        'returned_at' => date('Y-m-d H:i:s', strtotime($date.' +1 day')),
    ]);

    return $item;
}

it('aggregates eligible owned serialized assets from asset financial results', function (): void {
    $product = Product::factory()->rental()->serialised()->create();
    $first = makeProductFinancialAsset($product, ['base_acquisition_amount' => 20000]);
    $second = makeProductFinancialAsset($product, ['base_acquisition_amount' => 30000]);
    addProductRentalRevenue($first['asset'], 10000);
    addProductRentalRevenue($second['asset'], 60000);
    AssetExpense::factory()->create([
        'asset_id' => $second['asset']->id,
        'base_currency_code' => 'GBP',
        'base_amount' => 5000,
    ]);

    $external = makeProductFinancialAsset($product, [
        'ownership_type' => AssetOwnershipType::ExternallyOwned,
        'base_acquisition_amount' => 99999,
    ]);
    $bulk = makeProductFinancialAsset($product, ['base_acquisition_amount' => 99999], ['stock_category' => StockCategory::BulkStock]);
    $virtual = makeProductFinancialAsset($product, ['base_acquisition_amount' => 99999], ['is_virtual' => true]);
    makeProductFinancialAsset(Product::factory()->rental()->serialised()->create(), ['base_acquisition_amount' => 99999]);

    $result = app(ProductFinancialsCalculator::class)->calculate($product);

    expect($result->assetCount)->toBe(2)
        ->and($result->totalAcquisitionCost)->toBe(50000)
        ->and($result->confirmedAttributedRentalRevenue)->toBe(70000)
        ->and($result->operatingExpenses)->toBe(5000)
        ->and($result->netRentalIncome)->toBe(65000)
        ->and($result->costRecoveryRatio)->toBe('1.3000000000')
        ->and($result->remainingToBreakEven)->toBe(0)
        ->and($result->investmentProfit)->toBe(15000)
        ->and($result->investmentRoi)->toBe('0.3000000000')
        ->and($result->assetParticipationCount)->toBe(2)
        ->and($result->rentalJobCount)->toBe(2)
        ->and($result->recoveredAssetCount)->toBe(1)
        ->and($result->belowBreakEvenAssetCount)->toBe(1)
        ->and($result->unusedAssetCount)->toBe(0)
        ->and($result->incompleteAssetCount)->toBe(0);
});

it('keeps confirmed revenue while making fleet financial metrics unavailable for ambiguous assets', function (): void {
    $product = Product::factory()->rental()->serialised()->create();
    $complete = makeProductFinancialAsset($product, ['base_acquisition_amount' => 10000]);
    $ambiguous = makeProductFinancialAsset($product, ['base_acquisition_amount' => 10000]);
    $helper = Asset::factory()->create(['product_id' => $product->id]);
    $item = addProductRentalRevenue($complete['asset'], 20000);

    $ambiguousItem = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => 1,
    ]);
    $invoice = Invoice::factory()->issued()->create(['currency_code' => 'GBP', 'subtotal' => 9000, 'total' => 9000]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $ambiguousItem->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'total' => 9000,
        'currency_code' => 'GBP',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $ambiguous['asset']->id,
        'opportunity_item_id' => $ambiguousItem->id,
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-01 12:00:00',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $helper->id,
        'opportunity_item_id' => $ambiguousItem->id,
        'dispatched_at' => '2026-10-01 12:00:00',
        'returned_at' => '2026-10-02 10:00:00',
    ]);

    $result = app(ProductFinancialsCalculator::class)->calculate($product);

    expect($result->confirmedAttributedRentalRevenue)->toBe(20000)
        ->and($result->revenueComplete)->toBeFalse()
        ->and($result->financialsComplete)->toBeFalse()
        ->and($result->netRentalIncome)->toBeNull()
        ->and($result->costRecoveryRatio)->toBeNull()
        ->and($result->investmentRoi)->toBeNull()
        ->and($result->incompleteAssetCount)->toBe(1)
        ->and($result->assetParticipationCount)->toBe(2);
});

it('does not combine incompatible reporting currencies', function (): void {
    $product = Product::factory()->rental()->serialised()->create();
    $asset = makeProductFinancialAsset($product, [
        'base_currency_code' => 'EUR',
        'base_acquisition_amount' => 10000,
    ]);
    addProductRentalRevenue($asset['asset'], 20000);

    $result = app(ProductFinancialsCalculator::class)->calculate($product);

    expect($result->confirmedAttributedRentalRevenue)->toBeNull()
        ->and($result->financialsComplete)->toBeFalse()
        ->and($result->netRentalIncome)->toBeNull()
        ->and($result->investmentRoi)->toBeNull()
        ->and($result->unavailableReasons)->toContain('revenue_currency_unavailable');
});

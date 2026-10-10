<?php

use App\Enums\AssetRentalRevenueAttributionStatus;
use App\Enums\LineItemTransactionType;
use App\Models\Asset;
use App\Models\AssetUsage;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Assets\AssetRentalRevenueAttributor;
use Illuminate\Support\Carbon;

/**
 * @return array{item: OpportunityItem, invoiceItem: InvoiceItem, assets: list<Asset>}
 */
function createAttributionFixture(int $quantity = 1, int $amount = 10000): array
{
    $product = Product::factory()->rental()->serialised()->create();
    $item = OpportunityItem::factory()->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'transaction_type' => LineItemTransactionType::Rental,
        'quantity' => $quantity,
    ]);
    $invoice = Invoice::factory()->issued()->create([
        'subtotal' => $amount,
        'total' => $amount,
    ]);
    $invoiceItem = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $item->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'total' => $amount,
    ]);
    $assets = [];

    for ($index = 0; $index < $quantity; $index++) {
        $asset = Asset::factory()->create(['product_id' => $product->id]);
        AssetUsage::factory()->create([
            'asset_id' => $asset->id,
            'opportunity_item_id' => $item->id,
            'dispatched_at' => Carbon::parse('2026-10-01 10:00:00')->addMinutes($index),
            'returned_at' => '2026-10-04 10:00:00',
        ]);
        $assets[] = $asset;
    }

    return compact('item', 'invoiceItem', 'assets');
}

it('attributes a single rental unit to one Asset', function (): void {
    $fixture = createAttributionFixture(amount: 40000);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->amountForAsset($fixture['assets'][0]->id))->toBe(40000)
        ->and($result->total())->toBe(40000);
});

it('attributes four commercial rental units one-to-one', function (): void {
    $fixture = createAttributionFixture(quantity: 4, amount: 40000);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->allocations)->toHaveCount(4)
        ->and($result->allocations)->each->toBe(10000)
        ->and($result->total())->toBe(40000);
});

it('distributes integer remainder without losing revenue', function (): void {
    $fixture = createAttributionFixture(quantity: 3, amount: 10000);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->total())->toBe(10000)
        ->and(array_sum($result->allocations))->toBe(10000)
        ->and(max($result->allocations) - min($result->allocations))->toBe(1);
});

it('keeps a zero-value valid rental available', function (): void {
    $fixture = createAttributionFixture(quantity: 2, amount: 0);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->total())->toBe(0)
        ->and($result->allocations)->each->toBe(0);
});

it('marks sequential substitution ambiguous', function (): void {
    $fixture = createAttributionFixture(quantity: 1, amount: 40000);
    $item = $fixture['item'];
    $firstAsset = $fixture['assets'][0];
    $secondAsset = Asset::factory()->create(['product_id' => $firstAsset->product_id]);

    AssetUsage::query()->where('asset_id', $firstAsset->id)->update([
        'dispatched_at' => '2026-10-01 10:00:00',
        'returned_at' => '2026-10-01 14:00:00',
    ]);
    AssetUsage::factory()->create([
        'asset_id' => $secondAsset->id,
        'opportunity_item_id' => $item->id,
        'dispatched_at' => '2026-10-01 14:00:00',
    ]);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous)
        ->and($result->total())->toBeNull();
});

it('marks partial invoices without physical-unit mapping ambiguous', function (): void {
    $fixture = createAttributionFixture(quantity: 4, amount: 40000);
    $invoice = $fixture['invoiceItem']->invoice;
    $fixture['invoiceItem']->update(['quantity' => 2, 'total' => 20000]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $fixture['item']->id,
        'product_id' => $fixture['invoiceItem']->product_id,
        'quantity' => 2,
        'total' => 20000,
    ]);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh());

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous);
});

it('marks incomplete or mismatched physical participation ambiguous', function (): void {
    $fixture = createAttributionFixture(quantity: 2, amount: 20000);
    $fixture['assets'][1]->usages()->delete();

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous);

    $fixture = createAttributionFixture(quantity: 2, amount: 20000);
    $fixture['invoiceItem']->update(['quantity' => 1]);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh());

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous);
});

it('does not double-count staged invoice lines and marks them ambiguous', function (): void {
    $fixture = createAttributionFixture(quantity: 4, amount: 40000);
    $invoice = $fixture['invoiceItem']->invoice;
    $fixture['invoiceItem']->update(['quantity' => 2, 'total' => 20000]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'opportunity_item_id' => $fixture['item']->id,
        'product_id' => $fixture['invoiceItem']->product_id,
        'quantity' => 2,
        'total' => 20000,
    ]);

    $result = app(AssetRentalRevenueAttributor::class)->forAsset($fixture['assets'][0]);

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous)
        ->and($result->total())->toBeNull();
});

it('reduces valid attribution with included credit notes using the same participants', function (): void {
    $fixture = createAttributionFixture(quantity: 3, amount: 10000);
    $creditNote = CreditNote::factory()->create([
        'invoice_id' => $fixture['invoiceItem']->invoice_id,
        'currency_code' => $fixture['invoiceItem']->currency_code,
        'total' => -1000,
        'issued_at' => now(),
        'status' => 'issued',
    ]);
    CreditNoteItem::factory()->create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $fixture['invoiceItem']->id,
        'quantity' => 1,
        'total' => -1000,
    ]);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh());

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->total())->toBe(9000)
        ->and(array_sum($result->allocations))->toBe(9000);
});

it('keeps an ambiguous invoice attribution ambiguous even when it has a credit', function (): void {
    $fixture = createAttributionFixture(quantity: 1, amount: 40000);
    $secondAsset = Asset::factory()->create(['product_id' => $fixture['assets'][0]->product_id]);
    AssetUsage::factory()->create([
        'asset_id' => $secondAsset->id,
        'opportunity_item_id' => $fixture['item']->id,
        'dispatched_at' => '2026-10-01 14:00:00',
    ]);
    $creditNote = CreditNote::factory()->create(['invoice_id' => $fixture['invoiceItem']->invoice_id]);
    CreditNoteItem::factory()->create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $fixture['invoiceItem']->id,
    ]);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh());

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Ambiguous);
});

it('follows invoice and credit-note inclusion rules', function (): void {
    $fixture = createAttributionFixture(amount: 10000);
    $invoice = $fixture['invoiceItem']->invoice;

    $invoice->update(['status' => 'void']);
    expect(app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh())->status)
        ->toBe(AssetRentalRevenueAttributionStatus::NotApplicable);

    $fixture = createAttributionFixture(amount: 10000);
    $fixture['invoiceItem']->invoice->update(['type' => 'proforma']);
    expect(app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh())->status)
        ->toBe(AssetRentalRevenueAttributionStatus::NotApplicable);

    $fixture = createAttributionFixture(amount: 10000);
    $creditNote = CreditNote::factory()->create(['invoice_id' => $fixture['invoiceItem']->invoice_id]);
    CreditNoteItem::factory()->create([
        'credit_note_id' => $creditNote->id,
        'invoice_item_id' => $fixture['invoiceItem']->id,
        'total' => -1000,
    ]);
    $creditNote->update(['status' => 'void']);

    $result = app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh());

    expect($result->status)->toBe(AssetRentalRevenueAttributionStatus::Available)
        ->and($result->total())->toBe(10000);
});

it('returns not applicable for sale, service, and non-product lines', function (): void {
    $fixture = createAttributionFixture();
    $fixture['item']->update(['transaction_type' => LineItemTransactionType::Sale]);
    expect(app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh())->status)
        ->toBe(AssetRentalRevenueAttributionStatus::NotApplicable);

    $fixture = createAttributionFixture();
    $fixture['item']->update(['transaction_type' => LineItemTransactionType::Service]);
    expect(app(AssetRentalRevenueAttributor::class)->attribute($fixture['invoiceItem']->fresh())->status)
        ->toBe(AssetRentalRevenueAttributionStatus::NotApplicable);

    $invoiceItem = InvoiceItem::factory()->create([
        'opportunity_item_id' => null,
        'product_id' => null,
    ]);

    expect(app(AssetRentalRevenueAttributor::class)->attribute($invoiceItem)->status)
        ->toBe(AssetRentalRevenueAttributionStatus::NotApplicable);
});

<?php

namespace App\Services\Products;

use App\Enums\SupplierBillStatus;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ProductExternalRentalCostCalculator
{
    public function calculate(Product $product, ?CarbonImmutable $from = null, ?CarbonImmutable $until = null): ProductExternalRentalCostResult
    {
        $expectedItems = PurchaseOrderItem::query()
            ->with('purchaseOrder')
            ->where('product_id', $product->id)
            ->whereHas('purchaseOrder', function (Builder $query) use ($from, $until): void {
                $query->where('po_type', 'sub_hire')
                    ->whereIn('status', ['sent', 'partially_received', 'received']);

                if ($from !== null && $until !== null) {
                    $query->where(function (Builder $period) use ($from, $until): void {
                        $period->where(function (Builder $bounded) use ($from, $until): void {
                            $bounded->whereNotNull('starts_at')
                                ->whereNotNull('ends_at')
                                ->where('starts_at', '<', $until)
                                ->where('ends_at', '>', $from);
                        })->orWhere(function (Builder $undated) use ($from): void {
                            $undated->whereNull('starts_at')
                                ->where(function (Builder $created) use ($from): void {
                                    $created->whereNull('created_at')->orWhere('created_at', '>=', $from);
                                });
                        });
                    });
                }
            })
            ->get();

        $actualQuery = $product->supplierBillItems()
            ->whereHas('supplierBill', function (Builder $query) use ($from, $until): void {
                $query->where('status', SupplierBillStatus::Issued->value);

                if ($from !== null) {
                    $query->where('issued_at', '>=', $from);
                }

                if ($until !== null) {
                    $query->where('issued_at', '<', $until);
                }
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('purchaseOrderItem.purchaseOrder', fn (Builder $po) => $po->where('po_type', 'sub_hire'))
                    ->orWhereHas('virtualStockIntake')
                    ->orWhereHas('supplierBill.purchaseOrder', fn (Builder $po) => $po->where('po_type', 'sub_hire'));
            })
            ->with(['supplierBill', 'purchaseOrderItem']);

        $actualItems = $actualQuery->get();
        $currencies = $expectedItems->pluck('purchaseOrder.currency_code')
            ->merge($actualItems->pluck('supplierBill.currency_code'))
            ->filter()
            ->map(fn (string $currency): string => strtoupper($currency))
            ->unique()
            ->values();

        $reasons = [];

        if ($currencies->count() > 1) {
            $reasons[] = 'External costs use incompatible currencies.';

            return new ProductExternalRentalCostResult(
                productId: (int) $product->id,
                expectedCost: null,
                actualCost: null,
                variance: null,
                currencyCode: null,
                actualCostComplete: false,
                financialsComplete: false,
                unavailableReasons: $reasons,
            );
        }

        $currency = $currencies->first();
        $expected = $expectedItems->sum(fn (PurchaseOrderItem $item): int => (int) $item->total - (int) $item->tax);
        $actual = $actualItems->sum(fn ($item): int => $item->netAmount());
        $billedByItem = $actualItems->groupBy('purchase_order_item_id')->map(
            fn ($items): string => $items->sum(fn ($item): string => (string) $item->quantity),
        );

        $complete = $expectedItems->isEmpty() && $actualItems->isEmpty();

        foreach ($expectedItems as $expectedItem) {
            $billed = (float) ($billedByItem->get($expectedItem->id) ?? 0);

            if ($billed + 0.0001 < (float) $expectedItem->quantity) {
                $complete = false;
                break;
            }

            $complete = true;
        }

        if (! $complete) {
            $reasons[] = 'One or more sub-hire purchase lines have no complete issued supplier bill.';
        }

        return new ProductExternalRentalCostResult(
            productId: (int) $product->id,
            expectedCost: $expectedItems->isEmpty() ? null : $expected,
            actualCost: $actualItems->isEmpty() ? null : $actual,
            variance: $expectedItems->isEmpty() || ! $complete ? null : $actual - $expected,
            currencyCode: $currency,
            actualCostComplete: $complete,
            financialsComplete: $complete,
            unavailableReasons: $reasons,
        );
    }
}

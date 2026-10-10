<?php

namespace App\Services\Investment;

use App\Enums\InvoiceType;
use App\Enums\LineItemTransactionType;
use App\Models\InvoiceItem;
use App\Models\Product;
use Carbon\CarbonImmutable;

final class ProductWindowedRevenueReader
{
    public function read(Product $product, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): WindowedProductRevenueResult
    {
        $items = InvoiceItem::query()
            ->where('product_id', $product->id)
            ->whereHas('opportunityItem', fn ($query) => $query->where('transaction_type', LineItemTransactionType::Rental->value))
            ->whereHas('invoice', function ($query) use ($from, $until, $asOf): void {
                $query
                    ->whereNotNull('issued_at')
                    ->where('issued_at', '<', $until)
                    ->where('issued_at', '<=', $asOf)
                    ->where('issued_at', '>=', $from)
                    ->where('type', '!=', InvoiceType::Proforma->value)
                    ->where('status', '!=', 'void');
            })
            ->with(['invoice', 'creditNoteItems.creditNote'])
            ->get();

        $currencies = $items->pluck('currency_code')->filter()->map(fn (string $currency): string => strtoupper($currency))->unique();
        $reasons = [];

        if ($currencies->count() > 1) {
            return new WindowedProductRevenueResult(0, null, false, ['windowed_revenue_incompatible_currencies']);
        }

        $amount = 0;
        foreach ($items as $item) {
            $amount += (int) $item->total;

            foreach ($item->creditNoteItems as $creditNoteItem) {
                if ($creditNoteItem->creditNote?->issued_at !== null && $creditNoteItem->creditNote->status !== 'void') {
                    $amount -= abs((int) $creditNoteItem->total);
                }
            }
        }

        return new WindowedProductRevenueResult($amount, $currencies->first(), true, $reasons);
    }
}

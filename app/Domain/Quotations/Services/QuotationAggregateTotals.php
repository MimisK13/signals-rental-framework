<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\ValueObjects\QuotationLinePricing;

final class QuotationAggregateTotals
{
    /**
     * @param  list<int>  $excludedItemIds
     * @return array{net: int, tax: int, gross: int}
     */
    public function withLine(
        QuotationVersion $version,
        QuotationLinePricing $line,
        bool $isOptional,
        array $excludedItemIds = [],
    ): array {
        $totals = $this->without($version, $excludedItemIds);

        if (! $isOptional) {
            $totals['net'] += $line->netTotal;
            $totals['tax'] += $line->taxTotal;
            $totals['gross'] += $line->grossTotal;
        }

        return $totals;
    }

    /**
     * @param  list<int>  $excludedItemIds
     * @return array{net: int, tax: int, gross: int}
     */
    public function without(QuotationVersion $version, array $excludedItemIds): array
    {
        $items = $version->items()
            ->when($excludedItemIds !== [], fn ($query) => $query->whereNotIn('id', $excludedItemIds))
            ->where('is_optional', false)
            ->get(['total', 'tax_total', 'gross_total']);

        return [
            'net' => (int) $items->sum(fn (QuotationItem $item): int => $item->total),
            'tax' => (int) $items->sum(fn (QuotationItem $item): int => $item->tax_total),
            'gross' => (int) $items->sum(fn (QuotationItem $item): int => $item->gross_total),
        ];
    }
}

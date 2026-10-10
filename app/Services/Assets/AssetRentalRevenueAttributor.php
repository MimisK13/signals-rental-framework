<?php

namespace App\Services\Assets;

use App\Enums\AssetRentalRevenueAttributionStatus;
use App\Enums\InvoiceType;
use App\Enums\LineItemTransactionType;
use App\Models\Asset;
use App\Models\AssetUsage;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OpportunityItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AssetRentalRevenueAttributor
{
    public function attribute(InvoiceItem $invoiceItem): AssetRentalRevenueAttributionResult
    {
        $invoiceItem->loadMissing([
            'invoice',
            'opportunityItem.assetUsages.asset',
            'creditNoteItems.creditNote',
        ]);

        return $this->attributeLoaded(
            $invoiceItem,
            $this->relatedIncludedInvoiceItems($invoiceItem->opportunityItem),
        );
    }

    public function forAsset(Asset $asset): AssetRentalRevenueAttributionResult
    {
        $items = InvoiceItem::query()
            ->whereHas('invoice', fn (Builder $query): Builder => $this->applyIncludedInvoiceFilter($query))
            ->whereHas('opportunityItem', function (Builder $query) use ($asset): void {
                $query
                    ->where('transaction_type', LineItemTransactionType::Rental->value)
                    ->whereHas('assetUsages', fn (Builder $usageQuery): Builder => $usageQuery->where('asset_id', $asset->id));
            })
            ->with([
                'invoice',
                'opportunityItem.assetUsages.asset',
                'creditNoteItems.creditNote',
            ])
            ->get();

        if ($items->isEmpty()) {
            return $this->notApplicable();
        }

        $allocations = [];
        $currencyCode = null;

        foreach ($items->groupBy('opportunity_item_id') as $relatedItems) {
            foreach ($relatedItems as $item) {
                $result = $this->attributeLoaded($item, $relatedItems);

                if (! $result->isAvailable()) {
                    return $result;
                }

                $amount = $result->amountForAsset($asset->id);

                if ($amount !== null) {
                    $allocations[$asset->id] = ($allocations[$asset->id] ?? 0) + $amount;
                    $currencyCode ??= $result->currencyCode;
                }
            }
        }

        return new AssetRentalRevenueAttributionResult(
            status: AssetRentalRevenueAttributionStatus::Available,
            allocations: $allocations,
            currencyCode: $currencyCode,
        );
    }

    /**
     * @param  Collection<int, InvoiceItem>  $relatedItems
     */
    private function attributeLoaded(InvoiceItem $invoiceItem, Collection $relatedItems): AssetRentalRevenueAttributionResult
    {
        if (! $this->isIncludedInvoice($invoiceItem)
            || $invoiceItem->opportunityItem === null) {
            return $this->notApplicable();
        }

        $opportunityItem = $invoiceItem->opportunityItem;

        if ($opportunityItem->transaction_type !== LineItemTransactionType::Rental) {
            return $this->notApplicable();
        }

        if (! $opportunityItem->isProductBacked()) {
            return $this->notApplicable();
        }

        if ($invoiceItem->product_id === null) {
            return $this->notApplicable();
        }

        if ((int) $invoiceItem->product_id !== (int) $opportunityItem->itemable_id) {
            return $this->ambiguous();
        }

        if ($relatedItems->count() !== 1) {
            return $this->ambiguous();
        }

        $quantity = $this->positiveWholeQuantity((string) $invoiceItem->quantity);
        $lineQuantity = $this->positiveWholeQuantity((string) $opportunityItem->quantity);

        if ($quantity === null || $lineQuantity === null || $quantity !== $lineQuantity) {
            return $this->ambiguous();
        }

        /** @var Collection<int, AssetUsage> $usages */
        $usages = $opportunityItem->assetUsages;

        if (! $this->provesOneToOneParticipation($usages, $quantity)) {
            return $this->ambiguous();
        }

        $assetIds = $usages->pluck('asset_id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $allocations = $this->distribute((int) $invoiceItem->total, $assetIds);

        foreach ($invoiceItem->creditNoteItems as $creditNoteItem) {
            if (! $this->isIncludedCredit($creditNoteItem)) {
                continue;
            }

            if ($creditNoteItem->creditNote?->currency_code !== $invoiceItem->currency_code) {
                return $this->ambiguous();
            }

            $creditAllocations = $this->distribute(abs((int) $creditNoteItem->total), $assetIds);

            foreach ($creditAllocations as $assetId => $amount) {
                $allocations[$assetId] -= $amount;
            }
        }

        return new AssetRentalRevenueAttributionResult(
            status: AssetRentalRevenueAttributionStatus::Available,
            allocations: $allocations,
            currencyCode: $invoiceItem->currency_code,
        );
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    private function relatedIncludedInvoiceItems(?OpportunityItem $opportunityItem): Collection
    {
        if ($opportunityItem === null) {
            return collect();
        }

        return InvoiceItem::query()
            ->where('opportunity_item_id', $opportunityItem->id)
            ->whereHas('invoice', fn (Builder $query): Builder => $this->applyIncludedInvoiceFilter($query))
            ->with([
                'invoice',
                'opportunityItem.assetUsages.asset',
                'creditNoteItems.creditNote',
            ])
            ->get();
    }

    private function isIncludedInvoice(InvoiceItem $invoiceItem): bool
    {
        $invoice = $invoiceItem->invoice;

        return $invoice !== null
            && $invoice->issued_at !== null
            && $invoice->type !== InvoiceType::Proforma->value
            && $invoice->status !== 'void';
    }

    private function isIncludedCredit(CreditNoteItem $creditNoteItem): bool
    {
        $creditNote = $creditNoteItem->creditNote;

        return $creditNote !== null
            && $creditNote->status !== 'void';
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    private function applyIncludedInvoiceFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('issued_at')
            ->where('type', '!=', InvoiceType::Proforma->value)
            ->where('status', '!=', 'void');
    }

    /**
     * @param  Collection<int, AssetUsage>  $usages
     */
    private function provesOneToOneParticipation(Collection $usages, int $quantity): bool
    {
        if ($usages->count() !== $quantity
            || $usages->pluck('asset_id')->unique()->count() !== $quantity) {
            return false;
        }

        $events = [];

        foreach ($usages as $usage) {
            if ($usage->returned_at !== null && $usage->returned_at->lt($usage->dispatched_at)) {
                return false;
            }

            $start = $usage->dispatched_at->format('Y-m-d H:i:s.u');
            $events[$start]['starts'] = ($events[$start]['starts'] ?? 0) + 1;

            if ($usage->returned_at !== null) {
                $end = $usage->returned_at->format('Y-m-d H:i:s.u');
                $events[$end]['ends'] = ($events[$end]['ends'] ?? 0) + 1;
            }
        }

        ksort($events);
        $active = 0;
        $maximumConcurrent = 0;

        foreach ($events as $event) {
            $active -= $event['ends'] ?? 0;
            $active += $event['starts'] ?? 0;
            $maximumConcurrent = max($maximumConcurrent, $active);
        }

        return $maximumConcurrent === $quantity;
    }

    /**
     * @param  list<int>  $assetIds
     * @return array<int, int>
     */
    private function distribute(int $amount, array $assetIds): array
    {
        sort($assetIds);
        $count = count($assetIds);

        if ($count === 0) {
            return [];
        }

        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        $base = intdiv($absolute, $count);
        $remainder = $absolute % $count;
        $allocations = [];

        foreach ($assetIds as $index => $assetId) {
            $allocations[$assetId] = $sign * ($base + ($index < $remainder ? 1 : 0));
        }

        return $allocations;
    }

    private function positiveWholeQuantity(string $quantity): ?int
    {
        if (bccomp($quantity, '0', 2) <= 0 || bccomp($quantity, (string) (int) $quantity, 2) !== 0) {
            return null;
        }

        return (int) $quantity;
    }

    private function ambiguous(): AssetRentalRevenueAttributionResult
    {
        return new AssetRentalRevenueAttributionResult(AssetRentalRevenueAttributionStatus::Ambiguous);
    }

    private function notApplicable(): AssetRentalRevenueAttributionResult
    {
        return new AssetRentalRevenueAttributionResult(AssetRentalRevenueAttributionStatus::NotApplicable);
    }
}

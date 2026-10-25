<?php

namespace App\Services\Shortages;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Availability\DemandReadContext;
use App\Services\Availability\StockReadContext;
use App\ValueObjects\ShortageCollection;

/**
 * Collects shortages on peer opportunities at the same store for batch sub-hire.
 */
class ShortageCrossOpportunityScanner
{
    public function __construct(
        private readonly ShortageDetector $detector,
    ) {}

    /**
     * Merge the opportunity's shortages with unresolved shortages on other jobs at
     * the same store for the same products.
     */
    public function withPeerShortages(
        Opportunity $opportunity,
        ShortageCollection $localShortages,
        ?StockReadContext $stockContext = null,
        ?DemandReadContext $demandContext = null,
    ): ShortageCollection {
        $storeId = $opportunity->store_id;

        if ($storeId === null || $localShortages->isEmpty()) {
            return $localShortages;
        }

        $productIds = $localShortages
            ->map(static fn ($shortage): int => $shortage->productId)
            ->unique()
            ->values()
            ->all();

        $merged = new ShortageCollection($localShortages->all());

        $peerItems = OpportunityItem::query()
            ->where('opportunity_id', '!=', $opportunity->id)
            ->whereHas('opportunity', static fn ($query) => $query->where('store_id', $storeId))
            ->where(function ($query) use ($productIds): void {
                $query->where(function ($inner) use ($productIds): void {
                    $inner->where('itemable_type', Product::class)
                        ->whereIn('itemable_id', $productIds);
                })->orWhere(function ($inner) use ($productIds): void {
                    $inner->where('item_type', 'product')
                        ->whereIn('itemable_id', $productIds);
                });
            })
            ->with(['opportunity', 'item'])
            ->get();

        $shortagesByItem = [];

        // ShortageDetector::forItems() binds one Opportunity context to every
        // item in the batch, so peer items are batched only within their own
        // Opportunity. This preserves source identity while allowing the
        // availability engine to share product/store stock facts and retain
        // distinct booking-window demand facts.
        foreach ($peerItems->groupBy('opportunity_id') as $items) {
            $peerOpportunity = $items->first()?->opportunity;

            if (! $peerOpportunity instanceof Opportunity) {
                continue;
            }

            foreach ($this->detector->forItems(
                $items,
                $peerOpportunity,
                stockContext: $stockContext,
                demandContext: $demandContext,
            ) as $shortage) {
                $shortagesByItem[$shortage->opportunityItemId] = $shortage;
            }
        }

        foreach ($peerItems as $item) {
            $shortage = $shortagesByItem[$item->id] ?? null;

            if ($shortage === null || ! $shortage->isUnresolved()) {
                continue;
            }

            if ($merged->contains(static fn ($existing): bool => $existing->opportunityItemId === $shortage->opportunityItemId)) {
                continue;
            }

            $merged->push($shortage);
        }

        return $merged;
    }
}

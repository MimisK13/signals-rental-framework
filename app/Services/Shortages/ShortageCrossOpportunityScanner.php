<?php

namespace App\Services\Shortages;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
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
    public function withPeerShortages(Opportunity $opportunity, ShortageCollection $localShortages): ShortageCollection
    {
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

        foreach ($peerItems as $item) {
            $shortage = $this->detector->forItem($item);

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

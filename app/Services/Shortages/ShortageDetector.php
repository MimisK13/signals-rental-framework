<?php

namespace App\Services\Shortages;

use App\Enums\OpportunityState;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\ShortageResolutionItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\AvailabilityService;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Detects inventory shortages on opportunities by comparing line-item demand
 * against what the availability engine reports as free.
 */
class ShortageDetector
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly OpportunityItemDemandResolver $demandResolver,
    ) {}

    /**
     * @return ShortageCollection<int, Shortage>
     */
    public function forOpportunity(Opportunity $opportunity): ShortageCollection
    {
        $opportunity->loadMissing('items.item');
        $shortages = new ShortageCollection;

        foreach ($this->forItems($opportunity->items, $opportunity) as $shortage) {
            $shortages->push($shortage);
        }

        return $shortages;
    }

    public function forItem(OpportunityItem $item, ?Opportunity $opportunity = null): ?Shortage
    {
        $opportunity ??= $item->opportunity;

        return $this->forItems(new EloquentCollection([$item]), $opportunity)[$item->id] ?? null;
    }

    /**
     * Detect shortages for several lines while batching product, allocation,
     * resolution and availability reads. The calculation remains the same as
     * {@see forItem()} for every line.
     *
     * @param  Collection<int, OpportunityItem>  $items
     * @param  array<int, array<string, mixed>>  $contexts
     * @param  array<int, int>  $availableByItem
     * @param  array<int, Product>  $productsByItem
     * @param  array<int, int>  $allocatedByItem
     * @return array<int, Shortage>
     */
    public function forItems(
        Collection $items,
        Opportunity $opportunity,
        array $contexts = [],
        array $availableByItem = [],
        array $productsByItem = [],
        array $allocatedByItem = [],
    ): array {
        $itemIds = $items->map(static fn (OpportunityItem $item): int => $item->id)->all();
        $resolvedContexts = [];
        $productIds = [];

        foreach ($items as $item) {
            $context = $contexts[$item->id] ?? $this->demandResolver->resolveContext($item);
            $resolvedContexts[$item->id] = $context;

            if ($context['product_id'] !== null) {
                $productIds[] = $context['product_id'];
            }
        }

        $products = $productsByItem === []
            ? Product::query()
                ->whereIn('id', array_values(array_unique($productIds)))
                ->get()
                ->keyBy('id')
            : collect($productsByItem)->unique('id')->keyBy('id');
        $productsByItem = $productsByItem + $items->mapWithKeys(function (OpportunityItem $item) use ($products): array {
            $product = $item->relationLoaded('itemable') && $item->getRelation('itemable') instanceof Product
                ? $item->getRelation('itemable')
                : ($item->relationLoaded('item') && $item->item instanceof Product
                    ? $item->item
                    : $products->get($item->itemable_id));

            return $product === null ? [] : [$item->id => $product];
        })->all();

        if ($allocatedByItem === []) {
            $allocatedByItem = OpportunityItemAsset::query()
                ->whereIn('opportunity_item_id', $itemIds)
                ->whereNotNull('stock_level_id')
                ->selectRaw('opportunity_item_id, COUNT(*) as allocated_assets_count')
                ->groupBy('opportunity_item_id')
                ->pluck('allocated_assets_count', 'opportunity_item_id')
                ->map(static fn ($count): int => (int) $count)
                ->all();
        }

        $resolvedQuantities = ShortageResolutionItem::query()
            ->whereIn('opportunity_item_id', $itemIds)
            ->whereHas('resolution', static fn (Builder $query): Builder => $query->whereNotIn('status', [
                ShortageResolutionStatus::Cancelled->value,
                ShortageResolutionStatus::Failed->value,
            ])->where('resolution_type', '!=', ShortageResolutionType::Subhire->value))
            ->selectRaw('opportunity_item_id, SUM(quantity_allocated) as resolved_quantity')
            ->groupBy('opportunity_item_id')
            ->pluck('resolved_quantity', 'opportunity_item_id')
            ->map(static fn ($quantity): int => (int) $quantity)
            ->all();

        $shortages = [];

        foreach ($items as $item) {
            $context = $resolvedContexts[$item->id];
            $product = $productsByItem[$item->id] ?? null;
            $storeId = $context['store_id'];

            if ($product === null || $storeId === null || ! $product->track_availability) {
                continue;
            }

            $allocated = $allocatedByItem[$item->id] ?? 0;
            $requested = $allocated > 0
                ? $allocated
                : max(0, (int) round((float) $item->quantity));

            if ($requested <= 0) {
                continue;
            }

            $available = $availableByItem[$item->id] ?? $this->availability->availableForItem(
                $context['product_id'],
                $storeId,
                $context['from'],
                $context['to'],
                $this->demandResolver->sourceType(),
                $item->id,
            );
            $shortfall = $requested - $available;

            if ($shortfall <= 0) {
                continue;
            }

            $shortages[$item->id] = Shortage::make(
                opportunityItemId: $item->id,
                opportunityId: $opportunity->id,
                productId: $product->id,
                productName: $product->name,
                storeId: $storeId,
                requestedQuantity: $requested,
                availableQuantity: max(0, $available),
                trackingType: $product->stock_method ?? StockMethod::Bulk,
                startsAt: $context['from'],
                endsAt: $context['to'],
                isCritical: $opportunity->state === OpportunityState::Order,
                resolvedQuantity: $resolvedQuantities[$item->id] ?? 0,
            );
        }

        return $shortages;
    }
}

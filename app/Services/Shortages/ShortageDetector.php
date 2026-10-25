<?php

namespace App\Services\Shortages;

use App\Data\Opportunities\PreparedProductInputs;
use App\Data\Opportunities\PreparedStandardDemandInputs;
use App\Data\Opportunities\PreparedStockInputs;
use App\Enums\OpportunityState;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\ShortageResolutionItem;
use App\Services\Availability\DemandReadContext;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\StockReadContext;
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
    /** @var array<int, ShortageCollection> */
    private array $opportunityCache = [];

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly OpportunityItemDemandResolver $demandResolver,
    ) {}

    /**
     * @return ShortageCollection<int, Shortage>
     */
    public function forOpportunity(
        Opportunity $opportunity,
        ?StockReadContext $stockContext = null,
        ?DemandReadContext $demandContext = null,
    ): ShortageCollection {
        if (array_key_exists($opportunity->id, $this->opportunityCache)) {
            return $this->opportunityCache[$opportunity->id];
        }

        $opportunity->loadMissing('items.item');
        $shortages = new ShortageCollection;

        foreach ($this->forItems(
            $opportunity->items,
            $opportunity,
            stockContext: $stockContext,
            demandContext: $demandContext,
        ) as $shortage) {
            $shortages->push($shortage);
        }

        return $this->opportunityCache[$opportunity->id] = $shortages;
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
        ?StockReadContext $stockContext = null,
        ?DemandReadContext $demandContext = null,
        ?PreparedProductInputs $preparedProducts = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): array {
        $preparedPath = $preparedProducts !== null || $preparedStock !== null || $preparedDemand !== null;

        if ($preparedPath && ($preparedProducts === null || $preparedStock === null || $preparedDemand === null)) {
            throw new \LogicException('Complete prepared Product, stock, and Demand inputs are required for the prepared shortage path.');
        }

        $itemIds = $items->map(static fn (OpportunityItem $item): int => $item->id)->all();
        $resolvedContexts = [];

        foreach ($items as $item) {
            // The caller already knows the parent Opportunity. Bind it to each
            // line before resolving dates/store so the resolver does not issue
            // one identical Opportunity query per line item.
            $item->setRelation('opportunity', $opportunity);

            $context = $contexts[$item->id] ?? $this->demandResolver->resolveContext($item);
            $resolvedContexts[$item->id] = $context;
        }

        $products = $productsByItem === [] ? collect() : collect($productsByItem)->unique('id')->keyBy('id');

        if ($preparedProducts !== null) {
            foreach ($resolvedContexts as $context) {
                if ($context['product_id'] !== null) {
                    $productId = (int) $context['product_id'];
                    $products->put($productId, $preparedProducts->required($productId));
                }
            }
        }

        if ($productsByItem === [] && ! $preparedPath) {
            $missingProductIds = [];

            foreach ($items as $item) {
                if (($resolvedContexts[$item->id]['product_id'] ?? null) === null) {
                    continue;
                }

                $loadedProduct = $item->relationLoaded('item') && $item->item instanceof Product
                    ? $item->item
                    : ($item->relationLoaded('itemable') && $item->getRelation('itemable') instanceof Product
                        ? $item->getRelation('itemable')
                        : null);

                if ($loadedProduct instanceof Product) {
                    $products->put($loadedProduct->id, $loadedProduct);
                } else {
                    $missingProductIds[] = $resolvedContexts[$item->id]['product_id'];
                }
            }

            $missingProductIds = array_values(array_unique(array_filter($missingProductIds)));

            if ($missingProductIds !== []) {
                $products = $products->union(
                    Product::query()->whereIn('id', $missingProductIds)->get()->keyBy('id'),
                );
            }
        }

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

        $availabilityRequests = [];
        $availabilityRequestItems = [];
        $computedAvailableByItem = [];

        foreach ($items as $item) {
            if (array_key_exists($item->id, $availableByItem)) {
                continue;
            }

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

            $availabilityRequestItems[] = $item->id;
            $availabilityRequests[] = [
                'product_id' => $context['product_id'],
                'store_id' => $storeId,
                'from' => $context['from'],
                'to' => $context['to'],
                'exclude_source_type' => $this->demandResolver->sourceType(),
                'exclude_source_id' => $item->id,
            ];
        }

        if ($availabilityRequests !== []) {
            foreach ($this->availability->availableForItems(
                $availabilityRequests,
                $stockContext,
                $demandContext,
                products: $preparedProducts,
                preparedStock: $preparedStock,
                preparedDemand: $preparedDemand,
            ) as $index => $available) {
                $computedAvailableByItem[$availabilityRequestItems[$index]] = $available;
            }
        }

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

            $available = $availableByItem[$item->id]
                ?? $computedAvailableByItem[$item->id]
                ?? 0;
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

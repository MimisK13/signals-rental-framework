<?php

namespace App\Services\Planning;

use App\Data\Planning\OpportunityItemPlanningData;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\OwnedCapacityReader;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Data\OpportunityItemReadContext;
use App\Domain\Projects\Opportunities\Data\PreparedProductInputs;
use App\Domain\Projects\Opportunities\Data\PreparedStandardDemandInputs;
use App\Domain\Projects\Opportunities\Data\PreparedStockInputs;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Enums\StockMethod;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Composes the canonical, read-only planning state for opportunity lines.
 *
 * This class owns no planning state and performs no mutations. It delegates
 * context, availability, owned capacity, and shortage semantics to the
 * existing domain readers and joins their results in bounded batch reads.
 */
final class OpportunityItemPlanningReader
{
    public function __construct(
        private readonly OpportunityItemDemandResolver $demandResolver,
        private readonly AvailabilityService $availability,
        private readonly OwnedCapacityReader $ownedCapacity,
        private readonly ShortageDetector $shortages,
    ) {}

    public function read(OpportunityItem $item): ?OpportunityItemPlanningData
    {
        $item->loadMissing(['opportunity', 'item', 'dispatchStore']);
        $opportunity = $item->opportunity;

        if ($opportunity === null) {
            return null;
        }

        $opportunity->loadMissing('store');
        $item->setRelation('opportunity', $opportunity);

        return $this->readItems(new Collection([$item]), $opportunity)->first();
    }

    /**
     * @param  Collection<int, OpportunityItem>|null  $items
     * @param  array<int, OpportunityItemReadContext>  $preparedContexts
     * @return Collection<int, OpportunityItemPlanningData>
     */
    public function forOpportunity(
        Opportunity $opportunity,
        ?Collection $items = null,
        array $preparedContexts = [],
        ?PreparedProductInputs $preparedProducts = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): Collection {
        if ($items === null) {
            $opportunity->loadMissing(['store', 'items.item', 'items.dispatchStore']);
            $items = $opportunity->items;
        } else {
            if (! $opportunity->relationLoaded('store')) {
                $opportunity->loadMissing('store');
            }
        }

        $items->each(fn (OpportunityItem $item): OpportunityItem => $item->setRelation('opportunity', $opportunity));

        $contexts = array_map(
            static fn (OpportunityItemReadContext $context): array => $context->toResolvedArray(),
            $preparedContexts,
        );

        return $this->readItems($items, $opportunity, $contexts, $preparedProducts, $preparedStock, $preparedDemand);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @param  array<int, array{product_id: int|null, store_id: int|null, from: Carbon, to: Carbon, quantity: int, claim_quantity: int}>  $preparedContexts
     * @return Collection<int, OpportunityItemPlanningData>
     */
    private function readItems(
        Collection $items,
        Opportunity $opportunity,
        array $preparedContexts = [],
        ?PreparedProductInputs $preparedProducts = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): Collection {
        $preparedPath = $preparedProducts !== null || $preparedStock !== null || $preparedDemand !== null;

        if ($preparedPath && ($preparedProducts === null || $preparedStock === null || $preparedDemand === null)) {
            throw new \LogicException('Complete prepared Product, stock, and Demand inputs are required for the prepared planning path.');
        }

        if (! $preparedPath && $items instanceof EloquentCollection) {
            $items->loadMissing(['item', 'dispatchStore']);
        }
        $planningItems = $items->filter(static fn (OpportunityItem $item): bool => $item->isProductBacked())->values();

        if ($planningItems->isEmpty()) {
            return collect();
        }

        $missingProductRelation = ! $preparedPath && $planningItems->contains(
            static fn (OpportunityItem $item): bool => ! $item->relationLoaded('item'),
        );

        if ($missingProductRelation) {
            $loadedProducts = Product::query()
                ->whereIn('id', $planningItems->pluck('itemable_id')->filter()->unique()->values())
                ->get()
                ->keyBy('id');

            $planningItems->each(static function (OpportunityItem $item) use ($loadedProducts): void {
                $item->setRelation('item', $loadedProducts->get($item->itemable_id));
            });
        }

        $contexts = [];
        $products = [];
        $stores = [];
        $requests = [];
        $requestItemIds = [];

        foreach ($planningItems as $item) {
            $context = $preparedContexts[$item->id] ?? null;

            if ($context === null) {
                if ($preparedPath) {
                    throw new \LogicException("Prepared context for OpportunityItem {$item->id} is missing.");
                }

                $context = $this->demandResolver->resolveContext($item);
            }

            if ($context['product_id'] === null || $context['store_id'] === null) {
                continue;
            }

            $product = $preparedProducts?->required((int) $context['product_id']);
            $product ??= $item->relationLoaded('itemable') && $item->getRelation('itemable') instanceof Product
                ? $item->getRelation('itemable')
                : ($item->item instanceof Product ? $item->item : null);

            if (! $preparedPath) {
                $product ??= Product::query()->find($context['product_id']);
            }
            $store = $item->dispatch_store_id !== null && $item->dispatchStore instanceof Store
                ? $item->dispatchStore
                : ($opportunity->store instanceof Store
                    ? $opportunity->store
                    : ($preparedPath
                        ? null
                        : Store::query()->find($context['store_id'])));

            if ($product === null || $store === null) {
                continue;
            }

            $contexts[$item->id] = $context;
            $products[$item->id] = $product;
            $stores[$item->id] = $store;
            $requests[] = [
                'product_id' => $context['product_id'],
                'store_id' => $context['store_id'],
                'from' => $context['from'],
                'to' => $context['to'],
                'exclude_source_type' => $this->demandResolver->sourceType(),
                'exclude_source_id' => $item->id,
            ];
            $requestItemIds[] = $item->id;
        }

        if ($requests === []) {
            return collect();
        }

        $availableByItem = [];
        foreach ($this->availability->availableForItems(
            $requests,
            products: $preparedProducts,
            preparedStock: $preparedStock,
            preparedDemand: $preparedDemand,
        ) as $index => $available) {
            $availableByItem[$requestItemIds[$index]] = $available;
        }

        $ownedAvailableByItem = $this->ownedCapacity->availableForItems(
            $planningItems,
            $contexts,
            $preparedProducts,
            $preparedStock,
            $preparedDemand,
        );
        $allocatedByItem = $this->allocatedQuantities($planningItems, $products);
        $shortages = $this->shortages->forItems(
            $planningItems,
            $opportunity,
            $contexts,
            $availableByItem,
            $products,
            $allocatedByItem,
            preparedProducts: $preparedProducts,
            preparedStock: $preparedStock,
            preparedDemand: $preparedDemand,
        );
        $externalByItem = $this->externalSupplyQuantities($contexts, $preparedDemand);

        return $planningItems
            ->filter(fn (OpportunityItem $item): bool => isset($contexts[$item->id], $products[$item->id], $stores[$item->id]))
            ->map(function (OpportunityItem $item) use ($contexts, $products, $stores, $availableByItem, $ownedAvailableByItem, $allocatedByItem, $shortages, $externalByItem): OpportunityItemPlanningData {
                $context = $contexts[$item->id];
                $product = $products[$item->id];
                $store = $stores[$item->id];
                $requested = $this->decimalQuantity($item->quantity);
                $reserved = $item->reserved_quantity !== null
                    ? $this->decimalQuantity($item->reserved_quantity)
                    : null;
                $requestedUnits = max(0, (int) round((float) $item->quantity));
                $reservedUnits = $reserved === null ? null : max(0, (int) round((float) $item->reserved_quantity));
                $allocated = $product->stock_method === StockMethod::Serialised
                    ? ($allocatedByItem[$item->id] ?? 0)
                    : null;
                $allocationClaim = $reservedUnits ?? $context['claim_quantity'];
                $shortage = $shortages[$item->id] ?? null;

                return new OpportunityItemPlanningData(
                    opportunity_item_id: $item->id,
                    path: $item->path,
                    parent_path: $item->parentPath(),
                    depth: $item->depth(),
                    product_id: $product->id,
                    product_name: $product->name,
                    store_id: $store->id,
                    store_name: $store->name,
                    planning_starts_at: $this->timestamp($context['from']),
                    planning_ends_at: $this->timestamp($context['to']),
                    requested_quantity: $requested,
                    reserved_quantity: $reserved,
                    reservation_mode: $reserved === null ? 'legacy' : 'explicit',
                    reservation_state: $this->reservationState($reservedUnits, $requestedUnits),
                    effective_claim_quantity: $context['claim_quantity'],
                    reservation_gap: $this->decimalQuantity(
                        $reservedUnits === null ? 0 : max(0, $requestedUnits - $reservedUnits),
                    ),
                    available_quantity: $availableByItem[$item->id] ?? 0,
                    owned_available_quantity: $ownedAvailableByItem[$item->id] ?? 0,
                    external_supply_quantity: $externalByItem[$item->id] ?? 0,
                    shortage_quantity: $shortage === null ? 0 : $shortage->shortfall,
                    has_shortage: $shortage !== null && $shortage->isUnresolved(),
                    allocated_quantity: $allocated,
                    allocation_state: $allocated === null ? null : $this->allocationState($allocated, $allocationClaim),
                );
            })
            ->values();
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @param  array<int, Product>  $products
     * @return array<int, int>
     */
    private function allocatedQuantities(Collection $items, array $products): array
    {
        $serialisedIds = $items
            ->filter(fn (OpportunityItem $item): bool => isset($products[$item->id]) && $products[$item->id]->stock_method === StockMethod::Serialised)
            ->map(static fn (OpportunityItem $item): int => $item->id)
            ->all();
        $counts = array_fill_keys($items->map(static fn (OpportunityItem $item): int => $item->id)->all(), 0);

        if ($serialisedIds === []) {
            return $counts;
        }

        $allocated = OpportunityItemAsset::query()
            ->whereIn('opportunity_item_id', $serialisedIds)
            ->whereNotNull('stock_level_id')
            ->selectRaw('opportunity_item_id, COUNT(*) as allocated_count')
            ->groupBy('opportunity_item_id')
            ->pluck('allocated_count', 'opportunity_item_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        foreach ($allocated as $itemId => $count) {
            $counts[(int) $itemId] = $count;
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contexts
     * @return array<int, int>
     */
    private function externalSupplyQuantities(
        array $contexts,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): array {
        $results = [];
        $groups = [];

        foreach ($contexts as $itemId => $context) {
            $key = implode('|', [
                $context['product_id'],
                $context['store_id'],
                $context['from']->getTimestamp(),
                $context['to']->getTimestamp(),
            ]);
            $groups[$key][] = $itemId;
        }

        foreach ($groups as $itemIds) {
            $context = $contexts[$itemIds[0]];
            $demands = $preparedDemand?->required(
                $context['product_id'],
                $context['store_id'],
                $context['from'],
                $context['to'],
            ) ?? Demand::query()
                ->where('product_id', $context['product_id'])
                ->where('store_id', $context['store_id'])
                ->active()
                ->overlapping($context['from'], $context['to'])
                ->get();
            $quantity = (int) $demands
                ->filter(static fn (Demand $demand): bool => $demand->source_type === 'virtual_stock_intake' && $demand->quantity < 0)
                ->sum(fn (Demand $demand): int => abs($demand->effectiveQuantity()));

            foreach ($itemIds as $itemId) {
                $results[$itemId] = $quantity;
            }
        }

        return $results;
    }

    private function reservationState(?int $reserved, int $requested): string
    {
        if ($reserved === null) {
            return 'legacy';
        }

        if ($reserved <= 0) {
            return 'none';
        }

        return $reserved >= $requested ? 'full' : 'partial';
    }

    private function allocationState(int $allocated, int $claim): string
    {
        if ($allocated <= 0) {
            return 'none';
        }

        return $allocated >= $claim ? 'full' : 'partial';
    }

    private function decimalQuantity(int|float|string $quantity): string
    {
        return number_format((float) $quantity, 2, '.', '');
    }

    private function timestamp(Carbon $timestamp): string
    {
        return $timestamp->copy()->utc()->toIso8601String();
    }
}

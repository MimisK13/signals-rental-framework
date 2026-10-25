<?php

namespace App\Services\Availability;

use App\Data\Opportunities\PreparedProductInputs;
use App\Data\Opportunities\PreparedStandardDemandInputs;
use App\Data\Opportunities\PreparedStockInputs;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Reads owned capacity available to a reservation mutation.
 *
 * This deliberately excludes VirtualStock: external supply can fulfil a
 * requirement, but it cannot authorise an owned reservation.
 */
class OwnedCapacityReader
{
    public function __construct(private readonly RecalculationPipeline $pipeline) {}

    public function availableForItem(OpportunityItem $item): int
    {
        $results = $this->availableForItems([$item]);

        return $results[$item->id] ?? 0;
    }

    /**
     * Read owned capacity for several items while sharing stock and competing
     * demand reads for identical effective contexts.
     *
     * @param  iterable<OpportunityItem>  $items
     * @param  array<int, array{product_id: int|null, store_id: int|null, from: Carbon, to: Carbon}>  $contexts
     * @return array<int, int>
     */
    public function availableForItems(
        iterable $items,
        array $contexts = [],
        ?PreparedProductInputs $preparedProducts = null,
        ?PreparedStockInputs $preparedStock = null,
        ?PreparedStandardDemandInputs $preparedDemand = null,
    ): array {
        $resolvedItems = [];

        foreach ($items as $item) {
            $resolvedItems[$item->id] = [
                'item' => $item,
                'context' => $contexts[$item->id] ?? app(OpportunityItemDemandResolver::class)->resolveContext($item),
            ];
        }

        $results = [];
        $groups = [];

        foreach ($resolvedItems as $itemId => $resolved) {
            $context = $resolved['context'];

            if ($context['product_id'] === null || $context['store_id'] === null) {
                $results[$itemId] = 0;

                continue;
            }

            $key = implode('|', [
                $context['product_id'],
                $context['store_id'],
                $context['from']->getTimestamp(),
                $context['to']->getTimestamp(),
            ]);
            $groups[$key][] = $itemId;
        }

        $sourceType = app(OpportunityItemDemandResolver::class)->sourceType();

        foreach ($groups as $itemIds) {
            $firstContext = $resolvedItems[$itemIds[0]]['context'];
            $product = $preparedProducts?->required((int) $firstContext['product_id'])
                ?? Product::query()->findOrFail($firstContext['product_id']);
            $ownedStock = $preparedStock?->required(
                (int) $firstContext['product_id'],
                (int) $firstContext['store_id'],
            ) ?? $this->pipeline->totalStock($product, $firstContext['store_id']);

            /** @var Collection<int, Demand> $demands */
            $demands = $preparedDemand?->required(
                (int) $firstContext['product_id'],
                (int) $firstContext['store_id'],
                $firstContext['from'],
                $firstContext['to'],
            ) ?? Demand::query()
                ->where('product_id', $firstContext['product_id'])
                ->where('store_id', $firstContext['store_id'])
                ->active()
                ->overlapping($firstContext['from'], $firstContext['to'])
                ->get();

            foreach ($itemIds as $itemId) {
                $item = $resolvedItems[$itemId]['item'];
                $claimed = $demands
                    ->reject(static fn (Demand $demand): bool => $demand->source_type === $sourceType
                        && (int) $demand->source_id === (int) $item->id)
                    ->sum(static fn (Demand $demand): int => max(0, $demand->effectiveQuantity()));

                $results[$itemId] = max(0, $ownedStock - $claimed);
            }
        }

        return $results;
    }
}

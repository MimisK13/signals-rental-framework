<?php

namespace App\Services\Availability;

use App\Data\Opportunities\OpportunityItemReadContext;
use App\Data\Opportunities\PreparedStandardDemandInputs;
use App\Data\Opportunities\PreparedStockInputs;
use App\Enums\StockCategory;
use App\Models\Demand;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class PreparedAvailabilityInputReader
{
    /**
     * Read the canonical stock and active-demand facts for an editor tree in
     * two bounded queries, regardless of the number of unique item contexts.
     *
     * @param  array<int, OpportunityItemReadContext>  $contexts
     * @return array{0: PreparedStockInputs, 1: PreparedStandardDemandInputs}
     */
    public function forContexts(array $contexts): array
    {
        $contexts = array_values(array_filter(
            $contexts,
            static fn (OpportunityItemReadContext $context): bool => $context->productId !== null && $context->storeId !== null,
        ));

        if ($contexts === []) {
            return [new PreparedStockInputs([]), new PreparedStandardDemandInputs([])];
        }

        return [
            new PreparedStockInputs($this->stockByContext($contexts)),
            new PreparedStandardDemandInputs($this->demandsByContext($contexts)),
        ];
    }

    /**
     * @param  list<OpportunityItemReadContext>  $contexts
     * @return array<string, int>
     */
    private function stockByContext(array $contexts): array
    {
        $pairs = $this->uniqueProductStorePairs($contexts);
        $stockByKey = array_fill_keys(array_keys($pairs), 0);

        /** @var Collection<int, StockLevel> $rows */
        $rows = StockLevel::query()
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $query->orWhere(function (Builder $pairQuery) use ($pair): void {
                        $pairQuery
                            ->where('product_id', $pair['product_id'])
                            ->where('store_id', $pair['store_id']);
                    });
                }
            })
            ->where('is_virtual', false)
            ->where(function (Builder $query): void {
                $query
                    ->where('stock_category', '!=', StockCategory::SerialisedStock->value)
                    ->orWhere(function (Builder $serialized): void {
                        $serialized->operationalSerializedSupply();
                    });
            })
            ->selectRaw('product_id, store_id, stock_category as category, COUNT(*) as cnt, COALESCE(SUM(quantity_held), 0) as held')
            ->groupBy('product_id', 'store_id', 'stock_category')
            ->get();

        foreach ($rows as $row) {
            $key = PreparedStockInputs::key(
                (int) $row->getAttributeValue('product_id'),
                (int) $row->getAttributeValue('store_id'),
            );
            $category = (int) $row->getAttributeValue('category');

            if ($category === StockCategory::SerialisedStock->value) {
                $stockByKey[$key] += (int) $row->getAttributeValue('cnt');
            } elseif ($category === StockCategory::BulkStock->value) {
                $stockByKey[$key] += (int) round((float) $row->getAttributeValue('held'));
            }
        }

        return $stockByKey;
    }

    /**
     * @param  list<OpportunityItemReadContext>  $contexts
     * @return array<string, Collection<int, Demand>>
     */
    private function demandsByContext(array $contexts): array
    {
        $uniqueContexts = [];

        foreach ($contexts as $context) {
            $key = PreparedStandardDemandInputs::key(
                $context->productId,
                $context->storeId,
                $context->from,
                $context->to,
            );
            $uniqueContexts[$key] ??= $context;
        }

        $from = collect($uniqueContexts)->min(fn (OpportunityItemReadContext $context): int => $context->from->getTimestamp());
        $to = collect($uniqueContexts)->max(fn (OpportunityItemReadContext $context): int => $context->to->getTimestamp());
        $pairs = $this->uniqueProductStorePairs(array_values($uniqueContexts));

        /** @var Collection<int, Demand> $demands */
        $demands = Demand::query()
            ->where(function (Builder $query) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $query->orWhere(function (Builder $pairQuery) use ($pair): void {
                        $pairQuery
                            ->where('product_id', $pair['product_id'])
                            ->where('store_id', $pair['store_id']);
                    });
                }
            })
            ->active()
            ->overlapping(Carbon::createFromTimestampUTC($from), Carbon::createFromTimestampUTC($to))
            ->get();

        $demandsByKey = [];

        foreach ($uniqueContexts as $key => $context) {
            $demandsByKey[$key] = $demands
                ->filter(static fn (Demand $demand): bool => (int) $demand->product_id === $context->productId
                    && (int) $demand->store_id === $context->storeId
                    && $demand->bufferedStartsAt()->lessThan($context->to)
                    && $demand->bufferedEndsAt()->greaterThan($context->from))
                ->values();
        }

        return $demandsByKey;
    }

    /**
     * @param  list<OpportunityItemReadContext>  $contexts
     * @return array<string, array{product_id: int, store_id: int}>
     */
    private function uniqueProductStorePairs(array $contexts): array
    {
        $pairs = [];

        foreach ($contexts as $context) {
            $key = PreparedStockInputs::key($context->productId, $context->storeId);
            $pairs[$key] = [
                'product_id' => $context->productId,
                'store_id' => $context->storeId,
            ];
        }

        return $pairs;
    }
}

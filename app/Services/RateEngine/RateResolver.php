<?php

namespace App\Services\RateEngine;

use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves which {@see ProductRate} applies to a product for a given store,
 * transaction type, optional price category, and date. Matching rates are ranked
 * by priority (highest first), with store-specific rates preferred over all-stores
 * rates at equal priority. When a price category is supplied, category-specific
 * rates are tried first and the resolver falls back to default (null-category)
 * rates when none match. Returns null when nothing matches, in which case the
 * caller falls back to the product's default pricing (unit price × duration, no
 * engine logic).
 *
 * Results are cached per product. Writing a product rate invalidates that
 * product's cache; writing any rate definition invalidates all resolution
 * caches (a cached resolution carries its loaded definition, so a definition
 * edit must never serve stale config). Both invalidations are wired through the
 * models' lifecycle events.
 */
class RateResolver
{
    /** @var array<string, ProductRate|null> */
    private array $requestCache = [];

    /** @var array<int, RateDefinition> */
    private array $rateDefinitions = [];

    /**
     * Tag applied to every resolution cache entry; flushed when any rate
     * definition is written.
     */
    public const RESOLUTION_TAG = 'rate-resolution';

    public function resolve(
        Product $product,
        RateTransactionType $transactionType,
        ?int $storeId = null,
        ?CarbonInterface $date = null,
        ?int $priceCategoryId = null,
    ): ?ProductRate {
        $date ??= Carbon::now();

        $key = $this->resolutionKey($product->id, $transactionType, $storeId, $date, $priceCategoryId);

        if (array_key_exists($key, $this->requestCache)) {
            return $this->requestCache[$key];
        }

        if (! Cache::supportsTags()) {
            return $this->requestCache[$key] = $this->query($product, $transactionType, $storeId, $date, $priceCategoryId);
        }

        // Cap the TTL (max 1 hour) so an entry resolved for "today" cannot
        // outlive the day it was computed for. The date is part of the key, so a
        // new day already produces a fresh entry; this is belt-and-braces.
        $ttl = max(60, min(3600, (int) Carbon::now()->diffInSeconds($date->copy()->endOfDay(), false)));

        $resolved = Cache::tags([self::RESOLUTION_TAG, self::productTag($product->id)])
            ->remember($key, $ttl, fn (): ?ProductRate => $this->query($product, $transactionType, $storeId, $date, $priceCategoryId));

        $this->requestCache[$key] = $resolved;

        return $resolved;
    }

    /**
     * Resolve several products from one rate read while preserving the normal
     * per-product precedence rules.
     *
     * @param  iterable<Product>  $products
     * @return array<int, ProductRate|null>
     */
    public function resolveMany(
        iterable $products,
        RateTransactionType $transactionType,
        ?int $storeId = null,
        ?CarbonInterface $date = null,
        ?int $priceCategoryId = null,
    ): array {
        $date ??= Carbon::now();
        $products = collect($products)->unique('id')->values();
        $resolved = [];
        $missingProductIds = [];

        foreach ($products as $product) {
            $key = $this->resolutionKey($product->id, $transactionType, $storeId, $date, $priceCategoryId);

            if (array_key_exists($key, $this->requestCache)) {
                $resolved[$product->id] = $this->requestCache[$key];

                continue;
            }

            $missingProductIds[] = (int) $product->id;
        }

        if ($missingProductIds !== []) {
            $rates = ProductRate::query()
                ->whereIn('product_id', $missingProductIds)
                ->where('transaction_type', $transactionType)
                ->when(
                    $priceCategoryId === null,
                    fn ($query) => $query->whereNull('price_category_id'),
                    fn ($query) => $query->where(function ($query) use ($priceCategoryId): void {
                        $query->where('price_category_id', $priceCategoryId)
                            ->orWhereNull('price_category_id');
                    }),
                )
                ->where(function ($query) use ($storeId): void {
                    $query->whereNull('store_id');

                    if ($storeId !== null) {
                        $query->orWhere('store_id', $storeId);
                    }
                })
                ->where(function ($query) use ($date): void {
                    $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date->toDateString());
                })
                ->where(function ($query) use ($date): void {
                    $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date->toDateString());
                })
                ->with('rateDefinition')
                ->get()
                ->groupBy('product_id');

            foreach ($products as $product) {
                if (! in_array((int) $product->id, $missingProductIds, true)) {
                    continue;
                }

                $rate = $this->resolveFromCandidates(
                    $rates->get($product->id, collect()),
                    $storeId,
                    $date,
                    $priceCategoryId,
                );
                $key = $this->resolutionKey($product->id, $transactionType, $storeId, $date, $priceCategoryId);
                $this->requestCache[$key] = $rate;
                $resolved[$product->id] = $rate;
            }
        }

        return $resolved;
    }

    /**
     * Highest-priority product rate for the given criteria, or null.
     */
    private function query(
        Product $product,
        RateTransactionType $transactionType,
        ?int $storeId,
        CarbonInterface $date,
        ?int $priceCategoryId,
    ): ?ProductRate {
        if ($priceCategoryId !== null) {
            $categoryRate = $this->queryForPriceCategory(
                $product,
                $transactionType,
                $storeId,
                $date,
                $priceCategoryId,
            );

            if ($categoryRate !== null) {
                return $categoryRate;
            }
        }

        return $this->queryForPriceCategory($product, $transactionType, $storeId, $date, null);
    }

    /**
     * Highest-priority rate scoped to one price category (null = default rates).
     */
    private function queryForPriceCategory(
        Product $product,
        RateTransactionType $transactionType,
        ?int $storeId,
        CarbonInterface $date,
        ?int $priceCategoryId,
    ): ?ProductRate {
        // valid_from/valid_to are date columns (no time); compare on the calendar
        // date so a rate stays valid for the whole of its final day.
        $onDate = $date->toDateString();

        $rate = ProductRate::query()
            ->where('product_id', $product->id)
            ->where('transaction_type', $transactionType)
            ->when(
                $priceCategoryId === null,
                fn ($query) => $query->whereNull('price_category_id'),
                fn ($query) => $query->where('price_category_id', $priceCategoryId),
            )
            ->where(function ($query) use ($storeId): void {
                $query->whereNull('store_id');

                if ($storeId !== null) {
                    $query->orWhere('store_id', $storeId);
                }
            })
            ->where(function ($query) use ($onDate): void {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $onDate);
            })
            ->where(function ($query) use ($onDate): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $onDate);
            })
            ->orderByDesc('priority')
            ->orderByRaw('store_id IS NULL') // store-specific (non-null) first
            ->orderByDesc('id')
            ->first();

        if ($rate === null) {
            return null;
        }

        $rate->setRelation('rateDefinition', $this->rateDefinition($rate->rate_definition_id));

        return $rate;
    }

    /**
     * @param  Collection<int, ProductRate>  $rates
     */
    private function resolveFromCandidates(
        Collection $rates,
        ?int $storeId,
        CarbonInterface $date,
        ?int $priceCategoryId,
    ): ?ProductRate {
        $rate = $this->bestCandidate($rates, $storeId, $date, $priceCategoryId);

        if ($rate === null && $priceCategoryId !== null) {
            $rate = $this->bestCandidate($rates, $storeId, $date, null);
        }

        return $rate;
    }

    /**
     * @param  Collection<int, ProductRate>  $rates
     */
    private function bestCandidate(
        Collection $rates,
        ?int $storeId,
        CarbonInterface $date,
        ?int $priceCategoryId,
    ): ?ProductRate {
        $onDate = $date->toDateString();

        $rate = $rates
            ->filter(fn (ProductRate $rate): bool => $rate->price_category_id === $priceCategoryId
                && ($rate->store_id === null || ($storeId !== null && (int) $rate->store_id === $storeId))
                && ($rate->valid_from === null || $rate->valid_from->toDateString() <= $onDate)
                && ($rate->valid_to === null || $rate->valid_to->toDateString() >= $onDate))
            ->sort(function (ProductRate $left, ProductRate $right): int {
                return ($right->priority <=> $left->priority)
                    ?: ((int) ($left->store_id === null) <=> (int) ($right->store_id === null))
                    ?: ($right->id <=> $left->id);
            })
            ->first();

        if ($rate !== null && ! $rate->relationLoaded('rateDefinition')) {
            $rate->setRelation('rateDefinition', $this->rateDefinition($rate->rate_definition_id));
        }

        return $rate;
    }

    private function resolutionKey(
        int $productId,
        RateTransactionType $transactionType,
        ?int $storeId,
        CarbonInterface $date,
        ?int $priceCategoryId,
    ): string {
        return sprintf(
            'rate-resolve:%d:%s:%s:%s:%s',
            $productId,
            $storeId ?? 'all',
            $transactionType->value,
            $priceCategoryId ?? 'default',
            $date->toDateString(),
        );
    }

    /**
     * Flush every cached resolution for a single product.
     */
    public static function flushProduct(int $productId): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::productTag($productId)])->flush();
        }
    }

    /**
     * Flush every cached resolution (used when a rate definition changes).
     */
    public static function flushAll(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags([self::RESOLUTION_TAG])->flush();
        }
    }

    /**
     * Clear only the current request/job memoization after a rate mutation.
     * Persistent cache invalidation remains handled by the existing observers.
     */
    public function flushRequestCache(): void
    {
        $this->requestCache = [];
        $this->rateDefinitions = [];
    }

    private function rateDefinition(int $rateDefinitionId): RateDefinition
    {
        return $this->rateDefinitions[$rateDefinitionId]
            ??= RateDefinition::query()->findOrFail($rateDefinitionId);
    }

    private static function productTag(int $productId): string
    {
        return 'rate-product:'.$productId;
    }
}

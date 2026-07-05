<?php

namespace App\Services\Shortages;

use App\Models\ProductSupplierCost;
use Illuminate\Database\Eloquent\Collection;

/**
 * Loads supplier cost candidates for sub-hire shortage resolution.
 *
 * Store-scoped rows (exact store match) and store-agnostic rows (null store_id)
 * are included. Ordering: preferred first, then ascending cost.
 */
class SubHireSupplierCostQuery
{
    /**
     * @return Collection<int, ProductSupplierCost>
     */
    public function candidatesFor(int $productId, int $storeId): Collection
    {
        return ProductSupplierCost::query()
            ->forProduct($productId)
            ->where(function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->orWhereNull('store_id');
            })
            ->with('supplier')
            ->orderByDesc('is_preferred')
            ->orderBy('cost')
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$storeId])
            ->orderBy('id')
            ->get();
    }

    public function hasCandidatesFor(int $productId, int $storeId): bool
    {
        return ProductSupplierCost::query()
            ->forProduct($productId)
            ->where(function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->orWhereNull('store_id');
            })
            ->exists();
    }
}

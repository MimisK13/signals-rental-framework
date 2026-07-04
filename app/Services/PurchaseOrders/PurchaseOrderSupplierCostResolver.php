<?php

namespace App\Services\PurchaseOrders;

use App\Models\ProductSupplierCost;

/**
 * Resolves supplier unit costs for purchase order line pre-fill.
 *
 * Uses the per_unit cost basis and prefers is_preferred rows for the product,
 * supplier, and store (exact store match, then store-agnostic rows).
 */
class PurchaseOrderSupplierCostResolver
{
    public function resolve(int $productId, int $supplierId, int $storeId): ?ProductSupplierCost
    {
        $candidates = ProductSupplierCost::query()
            ->forProduct($productId)
            ->where('supplier_id', $supplierId)
            ->where('cost_basis', 'per_unit')
            ->where(function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->orWhereNull('store_id');
            })
            ->orderByDesc('is_preferred')
            ->orderByRaw('CASE WHEN store_id = ? THEN 0 ELSE 1 END', [$storeId])
            ->orderBy('id')
            ->get();

        return $candidates->first();
    }
}

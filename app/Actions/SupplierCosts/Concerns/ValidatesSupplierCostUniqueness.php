<?php

namespace App\Actions\SupplierCosts\Concerns;

use App\Models\ProductSupplierCost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait ValidatesSupplierCostUniqueness
{
    /**
     * Sibling scope for preferred-supplier logic: same product, same cost basis,
     * and the same store scope (null store_id = "all stores").
     *
     * @return Builder<ProductSupplierCost>
     */
    protected function siblingScopeQuery(ProductSupplierCost $cost): Builder
    {
        return ProductSupplierCost::query()
            ->where('product_id', $cost->product_id)
            ->where('cost_basis', $cost->cost_basis)
            ->when(
                $cost->store_id === null,
                fn (Builder $query): Builder => $query->whereNull('store_id'),
                fn (Builder $query): Builder => $query->where('store_id', $cost->store_id),
            );
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueSupplierCost(
        int $productId,
        int $supplierId,
        ?int $storeId,
        string $costBasis,
        ?int $exceptId = null,
    ): void {
        $exists = ProductSupplierCost::query()
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->where('cost_basis', $costBasis)
            ->when(
                $storeId === null,
                fn (Builder $query): Builder => $query->whereNull('store_id'),
                fn (Builder $query): Builder => $query->where('store_id', $storeId),
            )
            ->when(
                $exceptId !== null,
                fn (Builder $query): Builder => $query->where('id', '!=', $exceptId),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'supplier_id' => ['A supplier cost with this supplier, store scope, and cost basis already exists for this product.'],
            ]);
        }
    }

    protected function clearPreferredSiblings(ProductSupplierCost $cost): void
    {
        $this->siblingScopeQuery($cost)
            ->where('id', '!=', $cost->id)
            ->where('is_preferred', true)
            ->update(['is_preferred' => false]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Actions;

use App\Domain\Products\Models\Product;
use App\Domain\SupplierCosts\Actions\Concerns\ValidatesSupplierCostUniqueness;
use App\Domain\SupplierCosts\Data\ProductSupplierCostData;
use App\Domain\SupplierCosts\Data\UpdateSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Domain\SupplierCosts\Rules\EnsureSupplierCostCurrencyIsValid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateSupplierCost
{
    use ValidatesSupplierCostUniqueness;

    public function __invoke(ProductSupplierCost $cost, UpdateSupplierCostData $data): ProductSupplierCostData
    {
        Gate::authorize('update', $cost);
        $currencyCode = $data->currency_code !== null
            ? (new EnsureSupplierCostCurrencyIsValid)($data->currency_code)
            : null;

        return DB::transaction(function () use ($cost, $currencyCode, $data): ProductSupplierCostData {
            Product::query()->whereKey($cost->product_id)->lockForUpdate()->firstOrFail();
            $cost = ProductSupplierCost::query()->lockForUpdate()->findOrFail($cost->id);

            $nextSupplierId = $data->supplier_id ?? $cost->supplier_id;
            $nextStoreId = $data->store_id_provided ? $data->store_id : $cost->store_id;
            $nextCostBasis = $data->cost_basis ?? $cost->cost_basis;

            $this->assertUniqueSupplierCost(
                $cost->product_id,
                $nextSupplierId,
                $nextStoreId,
                $nextCostBasis,
                $cost->id,
            );

            $attributes = [];

            if ($data->supplier_id !== null) {
                $attributes['supplier_id'] = $nextSupplierId;
            }

            if ($data->cost_basis !== null) {
                $attributes['cost_basis'] = $nextCostBasis;
            }

            if ($data->cost !== null) {
                $attributes['cost'] = $data->cost;
            }

            if ($data->currency_code !== null) {
                $attributes['currency_code'] = $currencyCode;
            }

            if ($data->store_id_provided) {
                $attributes['store_id'] = $nextStoreId;
            }

            if ($data->supplier_sku_provided) {
                $attributes['supplier_sku'] = $data->supplier_sku;
            }

            if ($data->lead_time_days_provided) {
                $attributes['lead_time_days'] = $data->lead_time_days;
            }

            if ($data->min_order_quantity !== null) {
                $attributes['min_order_quantity'] = bcadd($data->min_order_quantity, '0', 2);
            }

            if ($data->metadata_provided) {
                $attributes['metadata'] = $data->metadata;
            }

            if ($data->is_preferred !== null) {
                $attributes['is_preferred'] = $data->is_preferred;
            }

            if ($attributes !== []) {
                $cost->fill($attributes);
                $cost->save();
            }

            if ($cost->is_preferred) {
                $this->clearPreferredSiblings($cost);
                $cost->refresh();
            }

            $cost->load(['supplier', 'store']);

            return ProductSupplierCostData::fromModel($cost);
        });
    }
}

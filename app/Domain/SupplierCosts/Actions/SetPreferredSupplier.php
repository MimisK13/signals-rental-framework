<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Actions;

use App\Domain\SupplierCosts\Actions\Concerns\ValidatesSupplierCostUniqueness;
use App\Domain\SupplierCosts\Data\ProductSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetPreferredSupplier
{
    use ValidatesSupplierCostUniqueness;

    public function __invoke(ProductSupplierCost $cost): ProductSupplierCostData
    {
        Gate::authorize('setPreferred', $cost);

        return DB::transaction(function () use ($cost): ProductSupplierCostData {
            Product::query()->whereKey($cost->product_id)->lockForUpdate()->firstOrFail();
            $cost = ProductSupplierCost::query()->lockForUpdate()->findOrFail($cost->id);

            $this->clearPreferredSiblings($cost);

            if (! $cost->is_preferred) {
                $cost->is_preferred = true;
                $cost->save();
            }

            $cost->load(['supplier', 'store']);

            return ProductSupplierCostData::fromModel($cost);
        });
    }
}

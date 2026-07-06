<?php

namespace App\Actions\SupplierCosts;

use App\Actions\SupplierCosts\Concerns\ValidatesSupplierCostUniqueness;
use App\Data\SupplierCosts\ProductSupplierCostData;
use App\Models\ProductSupplierCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetPreferredSupplier
{
    use ValidatesSupplierCostUniqueness;

    public function __invoke(ProductSupplierCost $cost): ProductSupplierCostData
    {
        Gate::authorize('setPreferred', $cost);

        return DB::transaction(function () use ($cost): ProductSupplierCostData {
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

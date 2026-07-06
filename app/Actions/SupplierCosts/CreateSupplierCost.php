<?php

namespace App\Actions\SupplierCosts;

use App\Actions\SupplierCosts\Concerns\ValidatesSupplierCostUniqueness;
use App\Data\SupplierCosts\CreateSupplierCostData;
use App\Data\SupplierCosts\ProductSupplierCostData;
use App\Models\ProductSupplierCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateSupplierCost
{
    use ValidatesSupplierCostUniqueness;

    public function __invoke(CreateSupplierCostData $data): ProductSupplierCostData
    {
        Gate::authorize('create', ProductSupplierCost::class);

        $this->assertUniqueSupplierCost(
            $data->product_id,
            $data->supplier_id,
            $data->store_id,
            $data->cost_basis,
        );

        return DB::transaction(function () use ($data): ProductSupplierCostData {
            $cost = ProductSupplierCost::create([
                'product_id' => $data->product_id,
                'supplier_id' => $data->supplier_id,
                'store_id' => $data->store_id,
                'cost' => $data->cost,
                'currency_code' => strtoupper($data->currency_code),
                'cost_basis' => $data->cost_basis,
                'supplier_sku' => $data->supplier_sku,
                'lead_time_days' => $data->lead_time_days,
                'min_order_quantity' => bcadd($data->min_order_quantity, '0', 2),
                'is_preferred' => $data->is_preferred,
                'metadata' => $data->metadata,
            ]);

            if ($data->is_preferred) {
                $this->clearPreferredSiblings($cost);
                $cost->refresh();
            }

            $cost->load(['supplier', 'store']);

            return ProductSupplierCostData::fromModel($cost);
        });
    }
}

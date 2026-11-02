<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Actions;

use App\Domain\SupplierCosts\Actions\Concerns\ValidatesSupplierCostUniqueness;
use App\Domain\SupplierCosts\Data\CreateSupplierCostData;
use App\Domain\SupplierCosts\Data\ProductSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Domain\SupplierCosts\Rules\EnsureSupplierCostCurrencyIsValid;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateSupplierCost
{
    use ValidatesSupplierCostUniqueness;

    public function __invoke(CreateSupplierCostData $data): ProductSupplierCostData
    {
        Gate::authorize('create', ProductSupplierCost::class);
        $currencyCode = (new EnsureSupplierCostCurrencyIsValid)($data->currency_code);

        return DB::transaction(function () use ($currencyCode, $data): ProductSupplierCostData {
            Product::query()->whereKey($data->product_id)->lockForUpdate()->firstOrFail();

            $this->assertUniqueSupplierCost(
                $data->product_id,
                $data->supplier_id,
                $data->store_id,
                $data->cost_basis,
            );

            $cost = ProductSupplierCost::create([
                'product_id' => $data->product_id,
                'supplier_id' => $data->supplier_id,
                'store_id' => $data->store_id,
                'cost' => $data->cost,
                'currency_code' => $currencyCode,
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

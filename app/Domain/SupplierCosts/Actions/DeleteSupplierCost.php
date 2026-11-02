<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Actions;

use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteSupplierCost
{
    public function __invoke(ProductSupplierCost $cost): void
    {
        Gate::authorize('delete', $cost);

        DB::transaction(function () use ($cost): void {
            Product::query()->whereKey($cost->product_id)->lockForUpdate()->firstOrFail();
            ProductSupplierCost::query()->lockForUpdate()->findOrFail($cost->id)->delete();
        });
    }
}

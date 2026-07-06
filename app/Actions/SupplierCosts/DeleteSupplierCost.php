<?php

namespace App\Actions\SupplierCosts;

use App\Models\ProductSupplierCost;
use Illuminate\Support\Facades\Gate;

class DeleteSupplierCost
{
    public function __invoke(ProductSupplierCost $cost): void
    {
        Gate::authorize('delete', $cost);

        $cost->delete();
    }
}

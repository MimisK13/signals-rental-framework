<?php

namespace App\Actions\Tax;

use App\Models\TaxCategory;
use Illuminate\Support\Facades\Gate;

class DeleteTaxCategory
{
    public function __invoke(TaxCategory $taxCategory): void
    {
        Gate::authorize('delete', $taxCategory);

        $taxCategory->delete();
    }
}

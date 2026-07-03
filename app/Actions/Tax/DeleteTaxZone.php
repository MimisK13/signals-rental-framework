<?php

namespace App\Actions\Tax;

use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class DeleteTaxZone
{
    public function __invoke(TaxZone $taxZone): void
    {
        Gate::authorize('delete', $taxZone);

        $taxZone->delete();
    }
}

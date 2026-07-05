<?php

namespace App\Actions\Pricing;

use App\Models\Surcharge;
use Illuminate\Support\Facades\Gate;

class DeleteSurcharge
{
    public function __invoke(Surcharge $surcharge): void
    {
        Gate::authorize('delete', $surcharge);

        $surcharge->delete();
    }
}

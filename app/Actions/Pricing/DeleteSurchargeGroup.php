<?php

namespace App\Actions\Pricing;

use App\Models\SurchargeGroup;
use Illuminate\Support\Facades\Gate;

class DeleteSurchargeGroup
{
    public function __invoke(SurchargeGroup $group): void
    {
        Gate::authorize('delete', $group);

        $group->delete();
    }
}

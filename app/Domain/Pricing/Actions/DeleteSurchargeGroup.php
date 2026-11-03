<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\SurchargeGroup;
use Illuminate\Support\Facades\Gate;

class DeleteSurchargeGroup
{
    public function __invoke(SurchargeGroup $group): void
    {
        Gate::authorize('delete', $group);

        $group->delete();
    }
}

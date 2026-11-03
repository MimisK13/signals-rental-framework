<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Models\Surcharge;
use Illuminate\Support\Facades\Gate;

class DeleteSurcharge
{
    public function __invoke(Surcharge $surcharge): void
    {
        Gate::authorize('delete', $surcharge);

        $surcharge->delete();
    }
}

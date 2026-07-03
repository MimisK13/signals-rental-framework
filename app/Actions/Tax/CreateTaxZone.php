<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxZoneData;
use App\Data\Tax\TaxZoneData;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class CreateTaxZone
{
    public function __invoke(CreateTaxZoneData $data): TaxZoneData
    {
        Gate::authorize('create', TaxZone::class);

        $taxZone = TaxZone::create($data->toArray());

        return TaxZoneData::fromModel($taxZone);
    }
}

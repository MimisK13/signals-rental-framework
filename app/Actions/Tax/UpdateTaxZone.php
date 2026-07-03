<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxZoneData;
use App\Data\Tax\UpdateTaxZoneData;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class UpdateTaxZone
{
    public function __invoke(TaxZone $taxZone, UpdateTaxZoneData $data): TaxZoneData
    {
        Gate::authorize('update', $taxZone);

        $taxZone->update(array_filter($data->toArray(), fn ($value): bool => $value !== null));

        return TaxZoneData::fromModel($taxZone->fresh());
    }
}

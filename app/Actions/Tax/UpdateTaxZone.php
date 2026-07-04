<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxZoneData;
use App\Data\Tax\UpdateTaxZoneData;
use App\Events\AuditableEvent;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class UpdateTaxZone
{
    public function __invoke(TaxZone $taxZone, UpdateTaxZoneData $data): TaxZoneData
    {
        Gate::authorize('update', $taxZone);

        $attributes = array_filter($data->toArray(), fn ($value): bool => $value !== null);

        $oldValues = [];
        $newValues = [];

        foreach ($attributes as $key => $value) {
            $oldValues[$key] = $taxZone->getAttribute($key);
            $newValues[$key] = $value;
        }

        $taxZone->update($attributes);

        if ($attributes !== []) {
            event(new AuditableEvent($taxZone, 'tax_zone.updated', $oldValues, $newValues));
        }

        return TaxZoneData::fromModel($taxZone->fresh());
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\TaxZoneData;
use App\Domain\Tax\Data\UpdateTaxZoneData;
use App\Domain\Tax\Models\TaxZone;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class UpdateTaxZone
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

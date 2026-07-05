<?php

namespace App\Actions\Tax;

use App\Data\Tax\CreateTaxZoneData;
use App\Data\Tax\TaxZoneData;
use App\Events\AuditableEvent;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class CreateTaxZone
{
    public function __invoke(CreateTaxZoneData $data): TaxZoneData
    {
        Gate::authorize('create', TaxZone::class);

        $taxZone = TaxZone::create($data->toArray());

        event(new AuditableEvent(
            $taxZone,
            'tax_zone.created',
            null,
            $this->auditSnapshot($taxZone),
        ));

        return TaxZoneData::fromModel($taxZone);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSnapshot(TaxZone $taxZone): array
    {
        return [
            'name' => $taxZone->name,
            'slug' => $taxZone->slug,
            'description' => $taxZone->description,
            'priority' => $taxZone->priority,
            'is_active' => $taxZone->is_active,
        ];
    }
}

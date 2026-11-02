<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\CreateTaxZoneData;
use App\Domain\Tax\Data\TaxZoneData;
use App\Domain\Tax\Models\TaxZone;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class CreateTaxZone
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

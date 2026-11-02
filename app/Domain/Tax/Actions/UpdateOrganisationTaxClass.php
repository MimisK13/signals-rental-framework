<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\TaxClassData;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class UpdateOrganisationTaxClass
{
    public function __invoke(OrganisationTaxClass $taxClass, UpdateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxClass, 'organisation_tax_class.updated'));

        return TaxClassData::fromModel($taxClass->fresh());
    }
}

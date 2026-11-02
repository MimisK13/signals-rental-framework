<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\TaxClassData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class CreateOrganisationTaxClass
{
    public function __invoke(CreateTaxClassData $data): TaxClassData
    {
        Gate::authorize('tax-classes.manage');

        $taxClass = OrganisationTaxClass::create($data->toArray());

        event(new AuditableEvent($taxClass, 'organisation_tax_class.created'));

        return TaxClassData::fromModel($taxClass);
    }
}

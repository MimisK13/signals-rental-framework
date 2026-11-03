<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Support\Facades\Gate;

final class DeleteOrganisationTaxClass
{
    public function __invoke(OrganisationTaxClass $taxClass): void
    {
        Gate::authorize('tax-classes.manage');

        event(new AuditableEvent($taxClass, 'organisation_tax_class.deleted'));

        $taxClass->delete();
    }
}

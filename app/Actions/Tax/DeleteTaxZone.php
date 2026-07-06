<?php

namespace App\Actions\Tax;

use App\Events\AuditableEvent;
use App\Models\TaxZone;
use Illuminate\Support\Facades\Gate;

class DeleteTaxZone
{
    public function __invoke(TaxZone $taxZone): void
    {
        Gate::authorize('delete', $taxZone);

        event(new AuditableEvent($taxZone, 'tax_zone.deleted', [
            'name' => $taxZone->name,
            'slug' => $taxZone->slug,
            'description' => $taxZone->description,
            'priority' => $taxZone->priority,
            'is_active' => $taxZone->is_active,
        ]));

        $taxZone->delete();
    }
}

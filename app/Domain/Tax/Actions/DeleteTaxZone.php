<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Models\TaxZone;
use App\Events\AuditableEvent;
use Illuminate\Support\Facades\Gate;

final class DeleteTaxZone
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

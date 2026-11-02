<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Models\TaxRate;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

final class DeleteTaxRate
{
    public function __invoke(TaxRate $taxRate): void
    {
        Gate::authorize('tax-classes.manage');

        $id = $taxRate->id;

        event(new AuditableEvent($taxRate, 'tax_rate.deleted'));

        $taxRate->delete();

        app(WebhookService::class)->dispatch('tax_rate.deleted', [
            'id' => $id,
        ]);
    }
}

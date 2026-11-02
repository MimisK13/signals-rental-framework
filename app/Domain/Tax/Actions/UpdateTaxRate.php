<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\TaxRateData;
use App\Domain\Tax\Data\UpdateTaxRateData;
use App\Domain\Tax\Models\TaxRate;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

final class UpdateTaxRate
{
    public function __invoke(TaxRate $taxRate, UpdateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxRate, 'tax_rate.updated'));

        $data = TaxRateData::fromModel($taxRate->fresh());

        app(WebhookService::class)->dispatch('tax_rate.updated', [
            'tax_rate' => $data->toArray(),
        ]);

        return $data;
    }
}

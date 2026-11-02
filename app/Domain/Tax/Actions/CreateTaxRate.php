<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\CreateTaxRateData;
use App\Domain\Tax\Data\TaxRateData;
use App\Domain\Tax\Models\TaxRate;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

final class CreateTaxRate
{
    public function __invoke(CreateTaxRateData $data): TaxRateData
    {
        Gate::authorize('tax-classes.manage');

        $taxRate = TaxRate::create($data->toArray());

        event(new AuditableEvent($taxRate, 'tax_rate.created'));

        app(WebhookService::class)->dispatch('tax_rate.created', [
            'tax_rate' => TaxRateData::fromModel($taxRate)->toArray(),
        ]);

        return TaxRateData::fromModel($taxRate);
    }
}

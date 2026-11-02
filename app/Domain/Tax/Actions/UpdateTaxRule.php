<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\TaxRuleData;
use App\Domain\Tax\Data\UpdateTaxRuleData;
use App\Domain\Tax\Models\TaxRule;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

final class UpdateTaxRule
{
    public function __invoke(TaxRule $taxRule, UpdateTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($taxRule, 'tax_rule.updated'));

        $data = TaxRuleData::fromModel($taxRule->fresh());

        app(WebhookService::class)->dispatch('tax_rule.updated', [
            'tax_rule' => $data->toArray(),
        ]);

        return $data;
    }
}

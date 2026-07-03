<?php

namespace App\Actions\Tax;

use App\Data\Tax\TaxRuleData;
use App\Data\Tax\UpdateZoneTaxRuleData;
use App\Events\AuditableEvent;
use App\Models\TaxRule;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class UpdateZoneTaxRule
{
    public function __invoke(TaxRule $taxRule, UpdateZoneTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule->update(array_filter($data->toArray(), fn ($value): bool => $value !== null));

        event(new AuditableEvent($taxRule, 'tax_rule.updated'));

        app(WebhookService::class)->dispatch('tax_rule.updated', [
            'tax_rule' => TaxRuleData::fromModel($taxRule->fresh())->toArray(),
        ]);

        return TaxRuleData::fromModel($taxRule->fresh());
    }
}

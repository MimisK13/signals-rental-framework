<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\Tax\Data\CreateTaxRuleData;
use App\Domain\Tax\Data\TaxRuleData;
use App\Domain\Tax\Models\TaxRule;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Facades\Gate;

final class CreateTaxRule
{
    public function __invoke(CreateTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $taxRule = TaxRule::create($data->toArray());

        event(new AuditableEvent($taxRule, 'tax_rule.created'));

        app(WebhookService::class)->dispatch('tax_rule.created', [
            'tax_rule' => TaxRuleData::fromModel($taxRule)->toArray(),
        ]);

        return TaxRuleData::fromModel($taxRule);
    }
}

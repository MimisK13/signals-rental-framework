<?php

declare(strict_types=1);

namespace App\Domain\Tax\Actions;

use App\Domain\Tax\Data\TaxRuleData;
use App\Domain\Tax\Data\UpdateZoneTaxRuleData;
use App\Domain\Tax\Models\TaxRule;
use App\Events\AuditableEvent;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

final class UpdateZoneTaxRule
{
    public function __invoke(TaxRule $taxRule, UpdateZoneTaxRuleData $data): TaxRuleData
    {
        Gate::authorize('tax-classes.manage');

        $attributes = array_filter($data->toArray(), fn ($value): bool => $value !== null);

        $oldValues = [];
        $newValues = [];

        foreach ($attributes as $key => $value) {
            $old = $taxRule->getAttribute($key);
            $oldValues[$key] = $old instanceof \BackedEnum ? $old->value : $old;
            $newValues[$key] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $taxRule->update($attributes);

        if ($attributes !== []) {
            event(new AuditableEvent($taxRule, 'tax_rule.updated', $oldValues, $newValues));
        }

        app(WebhookService::class)->dispatch('tax_rule.updated', [
            'tax_rule' => TaxRuleData::fromModel($taxRule->fresh())->toArray(),
        ]);

        return TaxRuleData::fromModel($taxRule->fresh());
    }
}

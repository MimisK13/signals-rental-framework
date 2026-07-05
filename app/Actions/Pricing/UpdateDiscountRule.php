<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\DiscountRuleData;
use App\Data\Pricing\UpdateDiscountRuleData;
use App\Events\AuditableEvent;
use App\Models\DiscountRule;
use Illuminate\Support\Facades\Gate;

class UpdateDiscountRule
{
    public function __invoke(DiscountRule $rule, UpdateDiscountRuleData $data): DiscountRuleData
    {
        Gate::authorize('update', $rule);

        $attributes = array_filter([
            'discount_percent' => $data->discount_percent,
            'criteria' => $data->criteria,
            'rule_scope' => $data->rule_scope,
            'valid_from' => $data->valid_from,
            'valid_to' => $data->valid_to,
            'sort_order' => $data->sort_order,
        ], fn (mixed $value): bool => $value !== null);

        $rule->update($attributes);

        event(new AuditableEvent($rule, 'discount_rule.updated'));

        return DiscountRuleData::fromModel($rule->refresh());
    }
}

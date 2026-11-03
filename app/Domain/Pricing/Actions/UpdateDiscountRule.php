<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\DiscountRuleData;
use App\Domain\Pricing\Data\UpdateDiscountRuleData;
use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\AuditLog\Events\AuditableEvent;
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

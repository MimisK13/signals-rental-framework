<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\CreateDiscountRuleData;
use App\Data\Pricing\DiscountRuleData;
use App\Events\AuditableEvent;
use App\Models\DiscountRule;
use Illuminate\Support\Facades\Gate;

class CreateDiscountRule
{
    public function __invoke(CreateDiscountRuleData $data): DiscountRuleData
    {
        Gate::authorize('create', DiscountRule::class);

        $rule = DiscountRule::query()->create([
            'discount_category_id' => $data->discount_category_id,
            'discount_percent' => $data->discount_percent,
            'criteria' => $data->criteria ?? [],
            'rule_scope' => $data->rule_scope,
            'valid_from' => $data->valid_from,
            'valid_to' => $data->valid_to,
            'sort_order' => $data->sort_order,
        ]);

        event(new AuditableEvent($rule, 'discount_rule.created'));

        return DiscountRuleData::fromModel($rule);
    }
}

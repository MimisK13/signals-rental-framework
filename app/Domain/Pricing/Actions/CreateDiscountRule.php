<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\CreateDiscountRuleData;
use App\Domain\Pricing\Data\DiscountRuleData;
use App\Domain\Pricing\Models\DiscountRule;
use App\Domain\AuditLog\Events\AuditableEvent;
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

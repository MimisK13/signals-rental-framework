<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\BundleRuleData;
use App\Data\Pricing\UpdateBundleRuleData;
use App\Events\AuditableEvent;
use App\Models\BundleRule;
use Illuminate\Support\Facades\Gate;

class UpdateBundleRule
{
    public function __invoke(BundleRule $rule, UpdateBundleRuleData $data): BundleRuleData
    {
        Gate::authorize('update', $rule);

        $rule->fill(array_filter([
            'name' => $data->name,
            'description' => $data->description,
            'bundle_percent' => $data->bundle_percent,
            'bundle_fixed_price_minor' => $data->bundle_fixed_price_minor,
            'criteria' => $data->criteria,
            'is_active' => $data->is_active,
            'sort_order' => $data->sort_order,
        ], fn (mixed $value): bool => $value !== null));

        $rule->save();

        event(new AuditableEvent($rule, 'bundle_rule.updated'));

        return BundleRuleData::fromModel($rule);
    }
}

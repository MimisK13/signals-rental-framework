<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\BundleRuleData;
use App\Data\Pricing\CreateBundleRuleData;
use App\Events\AuditableEvent;
use App\Models\BundleRule;
use Illuminate\Support\Facades\Gate;

class CreateBundleRule
{
    public function __invoke(CreateBundleRuleData $data): BundleRuleData
    {
        Gate::authorize('create', BundleRule::class);

        $rule = BundleRule::query()->create([
            'name' => $data->name,
            'description' => $data->description,
            'bundle_percent' => $data->bundle_percent,
            'bundle_fixed_price_minor' => $data->bundle_fixed_price_minor,
            'criteria' => $data->criteria ?? [],
            'is_active' => $data->is_active,
            'sort_order' => $data->sort_order,
        ]);

        event(new AuditableEvent($rule, 'bundle_rule.created'));

        return BundleRuleData::fromModel($rule);
    }
}

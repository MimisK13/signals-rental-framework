<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\BundleRuleData;
use App\Domain\Pricing\Data\UpdateBundleRuleData;
use App\Domain\Pricing\Models\BundleRule;
use App\Domain\AuditLog\Events\AuditableEvent;
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

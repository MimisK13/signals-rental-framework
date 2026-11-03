<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\BundleRuleData;
use App\Domain\Pricing\Data\CreateBundleRuleData;
use App\Domain\Pricing\Models\BundleRule;
use App\Domain\AuditLog\Events\AuditableEvent;
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

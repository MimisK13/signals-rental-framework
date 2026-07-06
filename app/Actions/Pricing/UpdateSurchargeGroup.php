<?php

namespace App\Actions\Pricing;

use App\Data\Pricing\SurchargeGroupData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Events\AuditableEvent;
use App\Models\SurchargeGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateSurchargeGroup
{
    public function __invoke(SurchargeGroup $group, UpdateSurchargeGroupData $data): SurchargeGroupData
    {
        Gate::authorize('update', $group);

        return DB::transaction(function () use ($group, $data): SurchargeGroupData {
            $group->update(array_filter([
                'name' => $data->name,
                'description' => $data->description,
                'is_default' => $data->is_default,
                'is_active' => $data->is_active,
                'sort_order' => $data->sort_order,
            ], fn (mixed $value): bool => $value !== null));

            if ($data->surcharges !== null) {
                (new SyncSurchargeGroupSurcharges)($group, $data->surcharges);
            }

            event(new AuditableEvent($group, 'surcharge_group.updated'));

            return SurchargeGroupData::fromModel($group->fresh(['surcharges.productTaxClass']), includeSurcharges: true);
        });
    }
}

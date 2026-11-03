<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\SurchargeGroupData;
use App\Domain\Pricing\Data\UpdateSurchargeGroupData;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\AuditLog\Events\AuditableEvent;
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

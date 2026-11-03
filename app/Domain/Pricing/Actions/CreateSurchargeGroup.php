<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Data\CreateSurchargeGroupData;
use App\Domain\Pricing\Data\SurchargeGroupData;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\AuditLog\Events\AuditableEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateSurchargeGroup
{
    public function __invoke(CreateSurchargeGroupData $data): SurchargeGroupData
    {
        Gate::authorize('create', SurchargeGroup::class);

        return DB::transaction(function () use ($data): SurchargeGroupData {
            $group = SurchargeGroup::query()->create([
                'name' => $data->name,
                'description' => $data->description,
                'is_default' => $data->is_default,
                'is_active' => $data->is_active,
                'sort_order' => $data->sort_order,
            ]);

            if ($data->surcharges !== []) {
                (new SyncSurchargeGroupSurcharges)($group, $data->surcharges);
            }

            event(new AuditableEvent($group, 'surcharge_group.created'));

            return SurchargeGroupData::fromModel($group->load('surcharges.productTaxClass'), includeSurcharges: true);
        });
    }
}

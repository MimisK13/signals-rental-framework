<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Events\AuditableEvent;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\InspectionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InitializeAssetInspection
{
    public function __invoke(
        Asset $asset,
        InspectionPolicy $policy,
        Carbon|string $dueAt,
        Carbon|string|null $scheduledStartAt = null,
        Carbon|string|null $scheduledEndAt = null,
    ): AssetInspection {
        Gate::authorize('stock.view');

        return DB::transaction(function () use ($asset, $policy, $dueAt, $scheduledStartAt, $scheduledEndAt): AssetInspection {
            $inspection = AssetInspection::query()->create([
                'asset_id' => $asset->id,
                'inspection_policy_id' => $policy->id,
                'due_at' => Carbon::parse($dueAt)->toDateString(),
                'scheduled_start_at' => $scheduledStartAt !== null ? Carbon::parse($scheduledStartAt) : null,
                'scheduled_end_at' => $scheduledEndAt !== null ? Carbon::parse($scheduledEndAt) : null,
                'status' => InspectionStatus::Pending,
            ]);
            event(new AuditableEvent($inspection, 'inspection.initialized'));

            return $inspection->refresh();
        });
    }
}

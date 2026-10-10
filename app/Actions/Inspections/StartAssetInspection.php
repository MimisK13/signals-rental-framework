<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Events\AuditableEvent;
use App\Models\AssetInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StartAssetInspection
{
    public function __invoke(AssetInspection $inspection): AssetInspection
    {
        Gate::authorize('stock.view');

        if ($inspection->statusEnum() !== InspectionStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending inspections can be started.']);
        }

        return DB::transaction(function () use ($inspection): AssetInspection {
            $inspection->forceFill(['status' => InspectionStatus::InProgress, 'started_at' => now()])->save();
            event(new AuditableEvent($inspection->refresh(), 'inspection.started'));

            return $inspection;
        });
    }
}

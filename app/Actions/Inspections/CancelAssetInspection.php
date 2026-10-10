<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Events\AuditableEvent;
use App\Models\AssetInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CancelAssetInspection
{
    public function __invoke(AssetInspection $inspection): AssetInspection
    {
        Gate::authorize('stock.view');

        if (! in_array($inspection->statusEnum(), [InspectionStatus::Pending, InspectionStatus::InProgress], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending or in-progress inspections can be cancelled.']);
        }

        return DB::transaction(function () use ($inspection): AssetInspection {
            $inspection->forceFill(['status' => InspectionStatus::Cancelled, 'result' => null])->save();
            event(new AuditableEvent($inspection->refresh(), 'inspection.cancelled'));

            return $inspection;
        });
    }
}

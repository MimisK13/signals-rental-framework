<?php

namespace App\Actions\Inspections;

use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Events\AuditableEvent;
use App\Models\AssetInspection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompleteAssetInspection
{
    public function __invoke(AssetInspection $inspection, InspectionResult|string $result): AssetInspection
    {
        Gate::authorize('stock.view');

        if ($inspection->statusEnum() !== InspectionStatus::InProgress) {
            throw ValidationException::withMessages(['status' => 'Only inspections in progress can be completed.']);
        }

        $result = $result instanceof InspectionResult ? $result : InspectionResult::from($result);

        return DB::transaction(function () use ($inspection, $result): AssetInspection {
            $inspection->forceFill([
                'status' => InspectionStatus::Completed,
                'result' => $result,
                'completed_at' => now(),
            ])->save();
            event(new AuditableEvent($inspection->refresh(), 'inspection.completed'));

            return $inspection;
        });
    }
}

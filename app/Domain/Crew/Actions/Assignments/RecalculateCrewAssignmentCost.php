<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RecalculateCrewAssignmentCost
{
    public function __invoke(CrewAssignment $assignment): CrewAssignmentData
    {
        Gate::authorize('view', $assignment);
        Gate::authorize('crew.rates.view');

        return DB::transaction(function () use ($assignment): CrewAssignmentData {
            StaffingGuard::memberLock($assignment->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::estimate($assignment);
            CrewAudit::record('crew.assignment.cost_recalculated', $assignment);

            return CrewAssignmentData::fromModel($assignment);
        });
    }
}

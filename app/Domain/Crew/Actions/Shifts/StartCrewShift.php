<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class StartCrewShift
{
    public function __invoke(CrewShift $shift): CrewShiftData
    {
        Gate::authorize('start', $shift);

        return DB::transaction(function () use ($shift): CrewShiftData {
            StaffingGuard::require($shift->crew_member_id !== null && $shift->crew_assignment_id !== null, 'crew_member_id', 'An assigned crew member is required.');
            StaffingGuard::memberLock($shift->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($shift->crew_assignment_id);
            $shift = CrewShift::query()->lockForUpdate()->findOrFail($shift->id);
            StaffingGuard::require($shift->status->canTransitionTo(CrewShiftStatus::InProgress), 'status', 'This shift cannot make the requested transition.');

            $shift->update(['status' => CrewShiftStatus::InProgress]);
            app(CrewAudit::class)->record('crew.shift.inprogress', $shift, []);

            return CrewShiftData::fromModel($shift);
        });
    }
}

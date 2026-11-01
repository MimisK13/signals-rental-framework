<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CancelCrewShiftData;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class CancelCrewShift
{
    public function __invoke(CrewShift $shift, CancelCrewShiftData $data): CrewShiftData
    {
        Gate::authorize('update', $shift);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:2000']])->validate();

        return DB::transaction(function () use ($shift, $data): CrewShiftData {
            StaffingGuard::require($shift->crew_member_id !== null && $shift->crew_assignment_id !== null, 'crew_member_id', 'An assigned crew member is required.');
            StaffingGuard::memberLock($shift->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($shift->crew_assignment_id);
            $shift = CrewShift::query()->lockForUpdate()->findOrFail($shift->id);
            StaffingGuard::require($shift->status->canTransitionTo(CrewShiftStatus::Cancelled), 'status', 'This shift cannot make the requested transition.');

            $shift->update(['status' => CrewShiftStatus::Cancelled]);
            StaffingGuard::estimate($assignment);
            app(CrewAudit::class)->record('crew.shift.cancelled', $shift, ['reason' => $data->reason]);

            return CrewShiftData::fromModel($shift);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Assignments;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Actions\Shifts\CreateCrewShift;
use App\Domain\Crew\Data\Assignments\CrewAssignmentData;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\SubstituteCrewAssignmentData;
use App\Domain\Crew\Data\Shifts\CreateCrewShiftData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class SubstituteCrewAssignment
{
    public function __invoke(CrewAssignment $assignment, SubstituteCrewAssignmentData $data, ?string $overrideReason = null): CrewAssignmentData
    {
        Gate::authorize('substitute', $assignment);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:2000'], 'crew_member_id' => ['required', 'integer', 'exists:crew_members,id']])->validate();
        StaffingGuard::require($data->crew_member_id !== $assignment->crew_member_id, 'crew_member_id', 'Choose a different crew member.');

        return DB::transaction(function () use ($assignment, $data, $overrideReason): CrewAssignmentData {
            $ids = [$assignment->crew_member_id, $data->crew_member_id];
            sort($ids);
            foreach ($ids as $id) {
                StaffingGuard::memberLock($id);
            }
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($assignment->crew_request_id);
            $line = CrewRequestLine::query()->lockForUpdate()->findOrFail($assignment->crew_request_line_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require($assignment->status === CrewAssignmentStatus::Confirmed, 'status', 'Only confirmed assignments may be substituted.');
            $shifts = $assignment->shifts()->where('status', '!=', 'cancelled')->get();
            StaffingGuard::require(! $shifts->contains(function (CrewShift $shift): bool {
                return in_array($shift->status, [CrewShiftStatus::InProgress, CrewShiftStatus::Completed], true);
            }), 'shifts', 'Worked assignments cannot be substituted.');
            $assignment->update(['status' => CrewAssignmentStatus::Substituted, 'active_assignment_key' => null]);
            foreach ($shifts as $shift) {
                $shift->update(['status' => CrewShiftStatus::Cancelled]);
                app(CrewAudit::class)->record('crew.shift.cancelled', $shift, ['reason' => $data->reason]);
            }
            $line->update(['filled_quantity' => $line->assignments()->where('status', 'confirmed')->count()]);
            $request->update(['status' => CrewRequestStatus::Open]);
            $proposed = app(ProposeCrewAssignment::class)($line, new ProposeCrewAssignmentData($data->crew_member_id, $assignment->currency_code), $overrideReason);
            $replacement = CrewAssignment::query()->findOrFail($proposed->id);
            $replacement->update(['substitute_for_assignment_id' => $assignment->id]);
            if ($data->copy_shifts) {
                foreach ($shifts as $shift) {
                    app(CreateCrewShift::class)($replacement, new CreateCrewShiftData(
                        $shift->starts_at->toIso8601String(), $shift->ends_at->toIso8601String(), $shift->timezone, $shift->type->value, $shift->break_minutes, $shift->location_name, $shift->notes,
                    ), $overrideReason);
                }
            }
            app(InviteCrewAssignment::class)($replacement);
            $result = app(ConfirmCrewAssignment::class)($replacement->refresh(), $overrideReason);
            app(CrewAudit::class)->record('crew.assignment.substituted', $assignment, ['replacement_assignment_id' => $replacement->id, 'reason' => $data->reason]);

            return $result;
        });
    }
}

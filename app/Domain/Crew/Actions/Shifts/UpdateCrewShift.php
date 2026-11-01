<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Data\Shifts\UpdateCrewShiftData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewShiftType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Services\CrewAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class UpdateCrewShift
{
    public function __invoke(CrewShift $shift, UpdateCrewShiftData $data, ?string $overrideReason = null): CrewShiftData
    {
        Gate::authorize('update', $shift);
        $assignment = $shift->assignment;
        StaffingGuard::require($assignment !== null, 'crew_assignment_id', 'This shift must belong to an assignment.');
        [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $data->timezone);
        Validator::make($data->toArray(), ['type' => ['required', Rule::enum(CrewShiftType::class)], 'break_minutes' => ['integer', 'min:0'], 'location_name' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string']])->validate();
        StaffingGuard::require($data->break_minutes < $start->diffInMinutes($end), 'break_minutes', 'Breaks must be shorter than the shift.');

        return DB::transaction(function () use ($assignment, $data, $start, $end, $overrideReason, $shift): CrewShiftData {
            StaffingGuard::memberLock($assignment->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require(in_array($assignment->status, [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Invited, CrewAssignmentStatus::Confirmed], true), 'status', 'The assignment is no longer active.');
            $shift = CrewShift::query()->lockForUpdate()->findOrFail($shift->id);
            StaffingGuard::require(! in_array($shift->status, [CrewShiftStatus::Completed, CrewShiftStatus::Cancelled], true), 'status', 'Completed or cancelled shifts cannot be edited.');
            $line = $assignment->line;
            StaffingGuard::require($start->gte($line->effectiveStartsAt()) && $end->lte($line->effectiveEndsAt()), 'starts_at', 'The shift must fall within its assignment period.');
            $report = app(CrewConflictQuery::class)->detect($assignment->crew_member_id, $start, $end, $shift->id, $assignment->id);
            StaffingGuard::require(! $assignment->shifts()->where('status', '!=', 'cancelled')->whereKeyNot($shift->id)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists(), 'starts_at', 'Shifts in the same assignment cannot overlap.');
            StaffingGuard::conflicts($report, $overrideReason);
            $attributes = ['crew_assignment_id' => $assignment->id, 'crew_member_id' => $assignment->crew_member_id, 'project_id' => $assignment->project_id, 'store_id' => $assignment->store_id,
                'starts_at' => $start, 'ends_at' => $end, 'timezone' => $data->timezone, 'type' => $data->type, 'break_minutes' => $data->break_minutes,
                'location_name' => $data->location_name, 'notes' => $data->notes, 'is_overnight' => $start->timezone($data->timezone)->toDateString() !== $end->timezone($data->timezone)->toDateString()];

            $shift->update($attributes);
            StaffingGuard::estimate($assignment);
            app(CrewAudit::class)->record('crew.shift.updated', $shift, ['override_reason' => $overrideReason, 'warnings' => $report->warnings]);

            return CrewShiftData::fromModel($shift);
        });
    }
}

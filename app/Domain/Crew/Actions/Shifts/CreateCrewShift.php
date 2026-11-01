<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CreateCrewShiftData;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
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

final class CreateCrewShift
{
    public function __invoke(CrewAssignment $assignment, CreateCrewShiftData $data, ?string $overrideReason = null): CrewShiftData
    {
        Gate::authorize('create', CrewShift::class);
        Gate::allowIf(auth()->user()->canAccessStore($assignment->store_id));
        [$start,$end] = StaffingGuard::dates($data->starts_at, $data->ends_at, $data->timezone);
        Validator::make($data->toArray(), ['type' => ['required', Rule::enum(CrewShiftType::class)], 'break_minutes' => ['integer', 'min:0'], 'location_name' => ['nullable', 'string', 'max:160'], 'notes' => ['nullable', 'string']])->validate();
        StaffingGuard::require($data->break_minutes < $start->diffInMinutes($end), 'break_minutes', 'Breaks must be shorter than the shift.');

        return DB::transaction(function () use ($assignment, $data, $start, $end, $overrideReason): CrewShiftData {
            StaffingGuard::memberLock($assignment->crew_member_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            StaffingGuard::require(in_array($assignment->status, [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Invited, CrewAssignmentStatus::Confirmed], true), 'status', 'The assignment is no longer active.');

            $line = $assignment->line;
            StaffingGuard::require($start->gte($line->effectiveStartsAt()) && $end->lte($line->effectiveEndsAt()), 'starts_at', 'The shift must fall within its assignment period.');
            $report = app(CrewConflictQuery::class)->detect($assignment->crew_member_id, $start, $end, null, $assignment->id);
            StaffingGuard::require(! $assignment->shifts()->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists(), 'starts_at', 'Shifts in the same assignment cannot overlap.');
            StaffingGuard::conflicts($report, $overrideReason);
            $attributes = ['crew_assignment_id' => $assignment->id, 'crew_member_id' => $assignment->crew_member_id, 'project_id' => $assignment->project_id, 'store_id' => $assignment->store_id,
                'starts_at' => $start, 'ends_at' => $end, 'timezone' => $data->timezone, 'type' => $data->type, 'break_minutes' => $data->break_minutes,
                'location_name' => $data->location_name, 'notes' => $data->notes, 'is_overnight' => $start->timezone($data->timezone)->toDateString() !== $end->timezone($data->timezone)->toDateString()];
            $attributes['status'] = CrewShiftStatus::Planned;
            $attributes['created_by'] = auth()->id();
            $shift = CrewShift::create($attributes);
            StaffingGuard::estimate($assignment);
            app(CrewAudit::class)->record('crew.shift.created', $shift, ['override_reason' => $overrideReason, 'warnings' => $report->warnings]);

            return CrewShiftData::fromModel($shift);
        });
    }
}

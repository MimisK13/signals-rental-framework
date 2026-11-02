<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Shifts;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Data\Shifts\RealignAssignmentShiftsData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Queries\CrewConflictQuery;
use App\Domain\Crew\Queries\CrewRequirementQuery;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RealignAssignmentShifts
{
    /** @return list<CrewShiftData> */
    public function __invoke(CrewAssignment $assignment, RealignAssignmentShiftsData $data, ?string $overrideReason = null): array
    {
        Gate::authorize('crew.shifts.manage');
        Gate::allowIf(auth()->user()->canAccessStore($assignment->store_id));
        Validator::make($data->toArray(), [
            'reason' => ['required', 'string', 'max:2000'], 'shifts' => ['required', 'array', 'min:1'],
            'shifts.*' => ['array:id,starts_at,ends_at,timezone'], 'shifts.*.id' => ['required', 'integer', 'distinct'],
            'shifts.*.starts_at' => ['required', 'date'], 'shifts.*.ends_at' => ['required', 'date'], 'shifts.*.timezone' => ['required', 'timezone'],
        ])->validate();

        return DB::transaction(function () use ($assignment, $data, $overrideReason): array {
            $member = StaffingGuard::memberLock($assignment->crew_member_id);
            $request = CrewRequest::query()->lockForUpdate()->findOrFail($assignment->crew_request_id);
            $lines = $request->lines()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $line = $lines->findOrFail($assignment->crew_request_line_id);
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            Gate::allowIf(auth()->user()->canAccessStore($assignment->store_id));
            StaffingGuard::require(in_array($assignment->status, [CrewAssignmentStatus::Proposed, CrewAssignmentStatus::Invited, CrewAssignmentStatus::Confirmed], true), 'status', 'Only active assignments may be realigned.');
            $project = Project::query()->sharedLock()->findOrFail($assignment->project_id);
            $oldLineStart = $line->effectiveStartsAt();
            $oldLineEnd = $line->effectiveEndsAt();
            $shifts = $assignment->shifts()->where('status', '!=', 'cancelled')->lockForUpdate()->get()->keyBy('id');
            $expected = $shifts->keys()->sort()->values()->all();
            $received = array_column($data->shifts, 'id');
            sort($received);
            StaffingGuard::require($expected === $received, 'shifts', 'Provide all active shifts exactly once.');
            $updates = [];
            foreach ($data->shifts as $row) {
                $shift = $shifts[$row['id']];
                StaffingGuard::require(in_array($shift->status, [CrewShiftStatus::Planned, CrewShiftStatus::Confirmed], true), 'shifts', 'Started or completed shifts cannot be realigned.');
                [$start,$end] = StaffingGuard::dates($row['starts_at'], $row['ends_at'], $row['timezone']);
                StaffingGuard::require($shift->break_minutes < $start->diffInMinutes($end), 'break_minutes', 'Breaks must be shorter than the realigned shift.');
                $insideLine = $start->gte($oldLineStart) && $end->lte($oldLineEnd);
                $insideProject = $project->starts_at !== null && $project->ends_at !== null && $start->gte($project->starts_at) && $end->lte($project->ends_at);
                StaffingGuard::require($insideLine || $insideProject, 'starts_at', 'The realigned shift must fall within the existing line period or the current project schedule.');
                $report = app(CrewConflictQuery::class)->detect($assignment->crew_member_id, $start, $end, ignoreAssignmentId: $assignment->id);
                StaffingGuard::conflicts($report, $overrideReason);
                $updates[] = ['shift' => $shift, 'start' => $start, 'end' => $end, 'timezone' => $row['timezone']];
            }
            usort($updates, function (array $a, array $b): int {
                return $a['start']->timestamp <=> $b['start']->timestamp;
            });
            foreach ($updates as $index => $update) {
                if ($index > 0) {
                    StaffingGuard::require($updates[$index - 1]['end']->lte($update['start']), 'shifts', 'Realigned shifts cannot overlap each other.');
                }
            }
            $newLineStart = $oldLineStart->min($updates[0]['start']);
            $newLineEnd = $oldLineEnd->max($updates[count($updates) - 1]['end']);
            if (! $newLineStart->eq($oldLineStart) || ! $newLineEnd->eq($oldLineEnd)) {
                $unresolvedSiblings = $line->assignments()->whereKeyNot($assignment->id)->whereIn('status', ['proposed', 'invited', 'confirmed'])
                    ->whereDoesntHave('shifts', function ($query): void {
                        $query->whereIn('status', ['confirmed', 'in_progress', 'completed']);
                    })->exists();
                StaffingGuard::require(! $unresolvedSiblings, 'shifts', 'Resolve the other active assignments on this role line before expanding its period. Their inherited booking windows must remain unchanged.');

                $oldRequestRange = ['starts_at' => $request->starts_at->toIso8601String(), 'ends_at' => $request->ends_at->toIso8601String()];
                $newRequestStart = $newLineStart->min($request->starts_at);
                $newRequestEnd = $newLineEnd->max($request->ends_at);
                $expandsRequest = ! $newRequestStart->eq($request->starts_at) || ! $newRequestEnd->eq($request->ends_at);
                if ($expandsRequest) {
                    // Resolve inherited sibling bounds before expanding their parent, preserving their effective dates.
                    foreach ($lines as $sibling) {
                        if ($sibling->id !== $line->id && ($sibling->starts_at === null || $sibling->ends_at === null)) {
                            $sibling->update(['starts_at' => $sibling->starts_at ?? $request->starts_at, 'ends_at' => $sibling->ends_at ?? $request->ends_at]);
                        }
                    }
                    $request->update(['starts_at' => $newRequestStart, 'ends_at' => $newRequestEnd]);
                    CrewAudit::record('crew.request.period_expanded', $request, ['reason' => $data->reason, 'assignment_id' => $assignment->id, 'old_range' => $oldRequestRange, 'new_range' => ['starts_at' => $newRequestStart->toIso8601String(), 'ends_at' => $newRequestEnd->toIso8601String()]]);
                }
                $line->update(['starts_at' => $newLineStart, 'ends_at' => $newLineEnd]);
                CrewAudit::record('crew.request.line_period_expanded', $request, ['reason' => $data->reason, 'line_id' => $line->id, 'assignment_id' => $assignment->id,
                    'old_range' => ['starts_at' => $oldLineStart->toIso8601String(), 'ends_at' => $oldLineEnd->toIso8601String()],
                    'new_range' => ['starts_at' => $newLineStart->toIso8601String(), 'ends_at' => $newLineEnd->toIso8601String()]]);
            }
            StaffingGuard::conflicts(app(CrewRequirementQuery::class)->forLine($member, $line), $overrideReason);
            $result = [];
            $oldRanges = [];
            $newRanges = [];
            foreach ($updates as $update) {
                $shift = $update['shift'];
                $oldRange = ['starts_at' => $shift->starts_at->toIso8601String(), 'ends_at' => $shift->ends_at->toIso8601String(), 'timezone' => $shift->timezone];
                $newRange = ['starts_at' => $update['start']->toIso8601String(), 'ends_at' => $update['end']->toIso8601String(), 'timezone' => $update['timezone']];
                $oldRanges[$shift->id] = $oldRange;
                $newRanges[$shift->id] = $newRange;
                $shift->update(['starts_at' => $update['start'], 'ends_at' => $update['end'], 'timezone' => $update['timezone'], 'is_overnight' => $update['start']->timezone($update['timezone'])->toDateString() !== $update['end']->timezone($update['timezone'])->toDateString()]);
                app(CrewAudit::class)->record('crew.shift.updated', $shift, ['reason' => $data->reason, 'override_reason' => $overrideReason, 'old_range' => $oldRange, 'new_range' => $newRange]);
                $result[] = CrewShiftData::fromModel($shift);
            }
            StaffingGuard::estimate($assignment);
            $assignment->update(['requires_realignment' => false, 'realignment_flagged_at' => null, 'realignment_reason' => null]);
            app(CrewAudit::class)->record('crew.assignment.realigned', $assignment, ['reason' => $data->reason, 'old_shift_ranges' => $oldRanges, 'new_shift_ranges' => $newRanges]);

            return $result;
        });
    }
}

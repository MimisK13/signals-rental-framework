<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CreateCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRateType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewAssignment $assignment, CreateCrewTimesheetData $data): CrewTimesheetData
    {
        Gate::authorize('create', CrewTimesheet::class);
        abort_unless(auth()->user()?->canAccessStore($assignment->store_id), 403);
        Validator::make($data->toArray(), ['period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start']])->validate();
        if (CarbonImmutable::parse($data->period_start)->diffInDays(CarbonImmutable::parse($data->period_end)) + 1 > (int) settings('crew.max_timesheet_period_days', 31)) {
            throw ValidationException::withMessages(['period_end' => 'The timesheet period exceeds the configured maximum.']);
        }

        return DB::transaction(function () use ($assignment, $data): CrewTimesheetData {
            CrewMember::query()->whereKey($assignment->crew_member_id)->lockForUpdate()->firstOrFail();
            $assignment = CrewAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless(auth()->user()?->canAccessStore($assignment->store_id), 403);
            $assignment->loadMissing('request');
            $from = CarbonImmutable::parse($data->period_start, $assignment->request->timezone)->utc();
            $to = CarbonImmutable::parse($data->period_end, $assignment->request->timezone)->addDay()->utc();
            $cancelledWithActualWork = $assignment->status === CrewAssignmentStatus::Cancelled
                && $assignment->confirmed_at !== null
                && $assignment->shifts()->where('status', 'completed')->where('starts_at', '>=', $from)->where('ends_at', '<=', $to)->exists();
            if ($assignment->status !== CrewAssignmentStatus::Confirmed && ! $cancelledWithActualWork) {
                throw ValidationException::withMessages(['crew_assignment_id' => 'A timesheet requires a confirmed assignment or completed work on a previously confirmed cancelled assignment.']);
            }
            $existing = $assignment->timesheets()->withTrashed();
            if ($assignment->rate_type !== CrewRateType::Fixed) {
                $existing->where('period_start', '<=', $data->period_end)->where('period_end', '>=', $data->period_start);
            }
            if ($existing->exists()) {
                throw ValidationException::withMessages(['period_start' => 'This assignment already has an overlapping timesheet or a fixed-rate claim.']);
            }
            $sheet = CrewTimesheet::query()->create(['crew_assignment_id' => $assignment->id, 'crew_member_id' => $assignment->crew_member_id, 'project_id' => $assignment->project_id, 'store_id' => $assignment->store_id, 'period_start' => $data->period_start, 'period_end' => $data->period_end, 'status' => 'draft', 'currency_code' => $assignment->currency_code, 'version' => 1, 'created_by' => auth()->id()]);
            if ($data->seed_from_shifts) {
                $assignment->loadMissing('request');
                $from = CarbonImmutable::parse($data->period_start, $assignment->request->timezone)->utc();
                $to = CarbonImmutable::parse($data->period_end, $assignment->request->timezone)->addDay()->utc();
                $shifts = $assignment->shifts()->whereIn('status', ['confirmed', 'in_progress', 'completed'])->where('starts_at', '>=', $from)->where('ends_at', '<=', $to)->orderBy('starts_at')->get();
                foreach ($shifts as $shift) {
                    $this->editor->saveEntry($sheet, ['started_at' => $shift->starts_at->toIso8601String(), 'ended_at' => $shift->ends_at->toIso8601String(), 'kind' => $shift->type->value === 'travel' ? 'travel' : ($shift->type->value === 'standby' ? 'standby' : 'work'), 'break_minutes' => $shift->break_minutes, 'crew_shift_id' => $shift->id]);
                }
            }
            $this->editor->recalculate($sheet);
            $sheet->save();
            CrewAudit::record('crew.timesheet.created', $sheet);

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

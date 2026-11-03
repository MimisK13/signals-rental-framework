<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewTimeEntryKind;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Stores\Scopes\StoreScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CrewTimesheetEditor
{
    public function __construct(private CrewCompensationCalculator $calculator, private CrewStatusRegistry $statuses) {}

    /** Must be called inside a transaction. Member lock serializes cross-assignment work. */
    public function lock(CrewTimesheet $sheet, string $ability, ?int $version = null): CrewTimesheet
    {
        CrewMember::query()->whereKey($sheet->crew_member_id)->lockForUpdate()->firstOrFail();
        $current = CrewTimesheet::query()->lockForUpdate()->findOrFail($sheet->id);
        Gate::authorize($ability, $current);
        if ($current->version !== ($version ?? $sheet->version)) {
            throw ValidationException::withMessages(['version' => 'This timesheet has changed. Reload it before saving.']);
        }

        return $current;
    }

    /** @param array<string,mixed> $attributes */
    public function saveEntry(CrewTimesheet $sheet, array $attributes, ?CrewTimeEntry $entry = null): CrewTimeEntry
    {
        if (! in_array($sheet->status, [CrewTimesheetStatus::Draft, CrewTimesheetStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or rejected timesheets can be edited.']);
        }
        $attributes = Validator::make($attributes, [
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'kind' => ['required', Rule::enum(CrewTimeEntryKind::class)],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'crew_shift_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        $start = CarbonImmutable::parse($attributes['started_at'])->utc();
        $end = CarbonImmutable::parse($attributes['ended_at'])->utc();
        $elapsed = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        if ($attributes['break_minutes'] >= $elapsed || $elapsed > 10080) {
            throw ValidationException::withMessages(['break_minutes' => 'The break must be shorter than a positive entry of at most seven days.']);
        }
        $sheet->loadMissing('assignment.request');
        $timezone = $sheet->assignment->request->timezone;
        $shift = null;
        if (($attributes['crew_shift_id'] ?? null) !== null) {
            $shift = $sheet->assignment->shifts()->whereKey($attributes['crew_shift_id'])->first();
            if ($shift === null) {
                throw ValidationException::withMessages(['crew_shift_id' => 'The shift must belong to this assignment.']);
            }
            $timezone = $shift->timezone;
        }
        if ($sheet->assignment->status === CrewAssignmentStatus::Cancelled && ($shift === null || $shift->status !== CrewShiftStatus::Completed || $sheet->assignment->cancelled_at === null || $end > $sheet->assignment->cancelled_at)) {
            throw ValidationException::withMessages(['crew_shift_id' => 'Time on a cancelled assignment must reference completed work before cancellation. Cancellation compensation is recorded separately.']);
        }
        $date = $start->setTimezone($timezone)->toDateString();
        if ($date < $sheet->period_start->toDateString() || $end->subSecond()->setTimezone($timezone)->toDateString() > $sheet->period_end->toDateString()) {
            throw ValidationException::withMessages(['started_at' => 'The whole entry must fall within the timesheet period.']);
        }
        $overlap = CrewTimeEntry::query()->whereHas('timesheet', function ($query) use ($sheet): void {
            $query->withoutGlobalScope(StoreScope::class)->where('crew_member_id', $sheet->crew_member_id);
        })->where('started_at', '<', $end)->where('ended_at', '>', $start);
        if ($entry !== null) {
            $overlap->whereKeyNot($entry->id);
        }
        if ($overlap->exists()) {
            throw ValidationException::withMessages(['started_at' => 'This member already has a time entry in this interval.']);
        }
        $entry ??= new CrewTimeEntry(['crew_timesheet_id' => $sheet->id, 'created_by' => auth()->id(), 'source' => 'manual']);
        $entry->fill($attributes + ['work_date' => $date, 'minutes' => $elapsed - $attributes['break_minutes'], 'currency_code' => $sheet->currency_code]);
        $entry->fill(['started_at' => $start, 'ended_at' => $end]);
        $entry->save();

        return $entry;
    }

    public function assertChronological(CrewTimesheet $sheet): void
    {
        $sheet->loadMissing('assignment.request');
        $entries = $sheet->entries()->with('shift')->get();
        if ($entries->isEmpty()) {
            return;
        }
        $policy = $sheet->assignment->allowances_snapshot['_policy'] ?? settings()->group('crew');
        $timezone = $sheet->assignment->request->timezone;
        $first = CarbonImmutable::instance($entries->first()->started_at);
        $last = CarbonImmutable::instance($entries->last()->ended_at);
        $weekStart = $first->setTimezone($timezone)->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->utc();
        $weekEnd = $last->setTimezone($timezone)->endOfWeek($policy['week_starts_on'] === 'sunday' ? 6 : 0)->utc();
        $later = CrewTimeEntry::query()->whereHas('timesheet', function ($query) use ($sheet): void {
            $query->withoutGlobalScope(StoreScope::class)->where('crew_member_id', $sheet->crew_member_id)->whereKeyNot($sheet->id)->whereIn('status', ['submitted', 'approved', 'locked']);
        })->where('started_at', '>', $first)->where('started_at', '<=', $weekEnd)->exists();
        if ($later) {
            throw ValidationException::withMessages(['period_start' => 'A later submitted or approved timesheet already uses this pay week. Process timesheets chronologically; return later submissions for correction before adding backdated time. Frozen pay requires a separate adjustment.']);
        }
        $earlierDraft = CrewTimeEntry::query()->whereHas('timesheet', function ($query) use ($sheet): void {
            $query->withoutGlobalScope(StoreScope::class)->where('crew_member_id', $sheet->crew_member_id)->whereKeyNot($sheet->id)->whereIn('status', ['draft', 'rejected']);
        })->where('started_at', '>=', $weekStart)->where('started_at', '<', $last)->exists();
        if ($earlierDraft) {
            throw ValidationException::withMessages(['period_start' => 'Submit earlier draft or rejected timesheets in this pay week first. Interleaved assignment periods must be split into chronological timesheets.']);
        }
    }

    public function recalculate(CrewTimesheet $sheet): void
    {
        $sheet->loadMissing('assignment.request');
        $entries = $sheet->entries()->with('shift')->get();
        if ($sheet->assignment->status === CrewAssignmentStatus::Cancelled) {
            $cancelledAt = $sheet->assignment->cancelled_at;
            foreach ($entries as $entry) {
                $shift = $entry->getRelation('shift');
                $completed = $shift instanceof CrewShift && $shift->status === CrewShiftStatus::Completed;
                $recordedBeforeCancellation = $entry->created_at !== null && $cancelledAt !== null && $entry->created_at <= $cancelledAt;
                if ($cancelledAt === null || $entry->ended_at > $cancelledAt || (! $completed && ! $recordedBeforeCancellation)) {
                    throw ValidationException::withMessages(['entries' => 'Remove unworked cancelled call entries before submission. Contractual cancellation pay is recorded separately.']);
                }
            }
        }
        $policy = $sheet->assignment->allowances_snapshot['_policy'] ?? settings()->group('crew');
        $timezone = $sheet->assignment->request->timezone;
        $from = CarbonImmutable::parse($sheet->period_start->toDateString(), $timezone)->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->utc();
        $previous = CrewTimeEntry::query()->with('timesheet')->whereHas('timesheet', function ($query) use ($sheet): void {
            $query->withoutGlobalScope(StoreScope::class)->where('crew_member_id', $sheet->crew_member_id)->whereIn('status', ['submitted', 'approved', 'locked'])->whereKeyNot($sheet->id);
        })->where('started_at', '>=', $from)->where('started_at', '<', $entries->isEmpty() ? $from : $entries->firstOrFail()->started_at)->get();
        $priorWeeks = [];
        $priorDays = [];
        foreach ($previous as $entry) {
            $local = CarbonImmutable::instance($entry->work_date);
            $week = $local->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->toDateString();
            $date = $local->toDateString();
            if ($entry->kind !== CrewTimeEntryKind::Travel || $policy['travel_earns_overtime']) {
                $priorDays[$date] = ($priorDays[$date] ?? 0) + $entry->minutes;
            }
            $priorWeeks[$week] = ($priorWeeks[$week] ?? 0) + $entry->minutes;
        }
        // Daily overtime must not count again towards weekly overtime.
        foreach ($priorDays as $date => $minutes) {
            $week = CarbonImmutable::parse($date, $timezone)->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->toDateString();
            $priorWeeks[$week] -= max(0, $minutes - (int) $policy['daily_overtime_threshold_hours'] * 60);
        }
        $accepted = CrewTimesheet::withoutGlobalScope(StoreScope::class)->whereIn('id', $previous->pluck('crew_timesheet_id')->unique())->get();
        $charged = [];
        foreach ($accepted as $priorSheet) {
            foreach ($priorSheet->overtime_breakdown ?? [] as $line) {
                if (($line['code'] ?? null) === 'CALL_MIN' && isset($line['date'], $line['minutes'])) {
                    $date = $line['date'];
                    $week = CarbonImmutable::parse($date, $timezone)->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->toDateString();
                    $regularMinimum = min($line['minutes'], max(0, (int) $policy['daily_overtime_threshold_hours'] * 60 - ($priorDays[$date] ?? 0)));
                    $priorWeeks[$week] = ($priorWeeks[$week] ?? 0) + $regularMinimum;
                    $priorDays[$date] = ($priorDays[$date] ?? 0) + $line['minutes'];
                }
                if ($priorSheet->crew_assignment_id === $sheet->crew_assignment_id && isset($line['quantity']) && $line['quantity'] > 0) {
                    $charged[] = $line['code'];
                }
            }
        }
        // Assignment-frequency allowances are paid once even across separate periods.
        $allAssignmentSheets = $sheet->assignment->timesheets()->whereKeyNot($sheet->id)->whereIn('status', ['submitted', 'approved', 'locked'])->get(['overtime_breakdown']);
        foreach ($allAssignmentSheets as $priorSheet) {
            foreach ($priorSheet->overtime_breakdown ?? [] as $line) {
                if (isset($line['quantity']) && $line['quantity'] > 0) {
                    $charged[] = $line['code'];
                }
            }
        }
        $calculationEntries = $entries->map(static function (CrewTimeEntry $entry) use ($timezone): array {
            $shift = $entry->getRelation('shift');

            return $entry->attributesToArray() + ['timezone' => $shift instanceof CrewShift ? $shift->timezone : $timezone];
        });
        $result = $this->calculator->calculate($sheet->assignment, $calculationEntries, ['prior_week_minutes' => $priorWeeks, 'prior_day_minutes' => $priorDays, 'prior_assignment_allowances' => array_unique($charged)]);
        foreach ($entries as $entry) {
            $entry->update($result['entries'][$entry->id]);
        }
        $sheet->fill(['total_minutes' => $result['total_minutes'], 'total_pay_minor' => $result['total_pay_minor'], 'rounding_rule' => $result['rounding_rule'], 'overtime_breakdown' => $result['breakdown']]);
    }

    /** @param array<string, mixed> $context */
    public function save(CrewTimesheet $sheet, string $event, array $context = []): void
    {
        if ($sheet->isDirty('status')) {
            $this->statuses->assertCanTransitionTo(CrewTimesheet::class, $sheet->getRawOriginal('status'), $sheet->status->value);
        }
        $sheet->version++;
        $sheet->save();
        CrewAudit::record($event, $sheet, $context);
    }
}

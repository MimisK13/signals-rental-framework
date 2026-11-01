<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Assignments;

use App\Domain\Crew\Actions\Assignments\CancelCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ConfirmCrewAssignment;
use App\Domain\Crew\Actions\Assignments\InviteCrewAssignment;
use App\Domain\Crew\Actions\Assignments\SubstituteCrewAssignment;
use App\Domain\Crew\Actions\Shifts\CancelCrewShift;
use App\Domain\Crew\Actions\Shifts\CompleteCrewShift;
use App\Domain\Crew\Actions\Shifts\ConfirmCrewShift;
use App\Domain\Crew\Actions\Shifts\CreateCrewShift;
use App\Domain\Crew\Actions\Shifts\RealignAssignmentShifts;
use App\Domain\Crew\Actions\Shifts\StartCrewShift;
use App\Domain\Crew\Actions\Shifts\UpdateCrewShift;
use App\Domain\Crew\Actions\Timesheets\CreateCrewTimesheet;
use App\Domain\Crew\Data\Assignments\CancelCrewAssignmentData;
use App\Domain\Crew\Data\Assignments\SubstituteCrewAssignmentData;
use App\Domain\Crew\Data\Shifts\CancelCrewShiftData;
use App\Domain\Crew\Data\Shifts\CreateCrewShiftData;
use App\Domain\Crew\Data\Shifts\RealignAssignmentShiftsData;
use App\Domain\Crew\Data\Shifts\UpdateCrewShiftData;
use App\Domain\Crew\Data\Timesheets\CreateCrewTimesheetData;
use App\Domain\Crew\Enums\CrewShiftType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use App\Support\Timezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * @property-read CrewAssignment $assignment
 * @property-read Collection<int, CrewShift> $shifts
 * @property-read Collection<int, CrewTimesheet> $timesheets
 * @property-read Collection<int, CrewMember> $replacementMembers
 */
#[Layout('components.layouts.app')]
#[Title('Crew assignment')]
final class Show extends CrewComponent
{
    #[Locked]
    public int $assignmentId;

    #[Locked]
    public ?int $editingShiftId = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public string $type = 'show';

    public int $break_minutes = 0;

    public string $period_start = '';

    public string $period_end = '';

    public string $reason = '';

    public ?int $replacement_member_id = null;

    public string $substitution_reason = '';

    public bool $copy_shifts = true;

    public ?string $override_reason = null;

    public bool $realigning = false;

    public string $realignment_reason = '';

    /** @var list<array{id: int, starts_at: string, ends_at: string}> */
    public array $realignmentShifts = [];

    public function mount(CrewAssignment $crewAssignment): void
    {
        Gate::authorize('view', $crewAssignment);
        $this->assignmentId = $crewAssignment->id;
        $this->copy_shifts = Gate::allows('crew.shifts.manage');
    }

    #[Computed]
    public function assignment(): CrewAssignment
    {
        $assignment = CrewAssignment::with(['crewMember', 'request', 'line.role', 'project'])->findOrFail($this->assignmentId);
        Gate::authorize('view', $assignment);

        return $assignment;
    }

    /** @return Collection<int, CrewShift> */
    #[Computed]
    public function shifts(): Collection
    {
        Gate::authorize('viewAny', CrewShift::class);

        return $this->assignment->shifts()->orderBy('starts_at')->get();
    }

    /** @return Collection<int, CrewTimesheet> */
    #[Computed]
    public function timesheets(): Collection
    {
        Gate::authorize('viewAny', CrewTimesheet::class);

        return $this->assignment->timesheets()->latest('period_start')->get();
    }

    /** @return Collection<int, CrewMember> */
    #[Computed]
    public function replacementMembers(): Collection
    {
        Gate::authorize('substitute', $this->assignment);

        return CrewMember::where('status', 'active')->whereKeyNot($this->assignment->crew_member_id)->orderBy('name')->get(['id', 'name']);
    }

    public function substitute(): void
    {
        Gate::authorize('substitute', $this->assignment);
        $this->validate([
            'replacement_member_id' => ['required', 'integer', 'exists:crew_members,id'],
            'substitution_reason' => ['required', 'string', 'max:2000'],
            'copy_shifts' => ['boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->performCrewAction(function (): void {
            $replacement = app(SubstituteCrewAssignment::class)($this->assignment, new SubstituteCrewAssignmentData(
                crew_member_id: $this->replacement_member_id,
                reason: $this->substitution_reason,
                copy_shifts: $this->copy_shifts,
            ), $this->override_reason);
            $this->redirectRoute('crew.assignments.show', ['crewAssignment' => $replacement->id], navigate: true);
        });
    }

    public function beginRealignment(): void
    {
        Gate::authorize('crew.shifts.manage');
        $this->realignmentShifts = $this->assignment->shifts()->where('status', '!=', 'cancelled')->orderBy('starts_at')->get()->map(function (CrewShift $shift): array {
            return [
                'id' => $shift->id,
                'starts_at' => app(Timezone::class)->toLocal($shift->starts_at)->format('Y-m-d\TH:i'),
                'ends_at' => app(Timezone::class)->toLocal($shift->ends_at)->format('Y-m-d\TH:i'),
            ];
        })->all();
        $this->realignment_reason = '';
        $this->realigning = true;
        $this->resetValidation();
    }

    public function cancelRealignment(): void
    {
        $this->reset('realigning', 'realignmentShifts', 'realignment_reason');
        $this->resetValidation();
    }

    public function realign(): void
    {
        Gate::authorize('crew.shifts.manage');
        $this->validate([
            'realignment_reason' => ['required', 'string', 'max:2000'],
            'realignmentShifts' => ['required', 'array', 'min:1'],
            'realignmentShifts.*.id' => ['required', 'integer', 'distinct'],
            'realignmentShifts.*.starts_at' => ['required', 'date'],
            'realignmentShifts.*.ends_at' => ['required', 'date'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->performCrewAction(function (): void {
            $rows = [];
            foreach ($this->realignmentShifts as $index => $row) {
                [$from, $to] = $this->crewRangeToUtc($row['starts_at'], $row['ends_at'], "realignmentShifts.{$index}.starts_at", "realignmentShifts.{$index}.ends_at");
                $rows[] = ['id' => $row['id'], 'starts_at' => $from, 'ends_at' => $to, 'timezone' => app(Timezone::class)->current()];
            }
            app(RealignAssignmentShifts::class)($this->assignment, new RealignAssignmentShiftsData($rows, $this->realignment_reason), $this->override_reason);
            $this->cancelRealignment();
            unset($this->assignment, $this->shifts);
        });
    }

    public function invite(): void
    {
        $this->performCrewAction(function (): void {
            app(InviteCrewAssignment::class)($this->assignment);
            unset($this->assignment);
        });
    }

    public function confirm(): void
    {
        $this->performCrewAction(function (): void {
            app(ConfirmCrewAssignment::class)($this->assignment);
            unset($this->assignment);
        });
    }

    public function cancel(): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->performCrewAction(function (): void {
            app(CancelCrewAssignment::class)($this->assignment, CancelCrewAssignmentData::from(['reason' => $this->reason]));
            unset($this->assignment,$this->shifts);
        });
    }

    public function addShift(): void
    {
        $data = $this->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'type' => ['required', Rule::enum(CrewShiftType::class)], 'break_minutes' => ['required', 'integer', 'min:0']]);
        [$data['starts_at'], $data['ends_at']] = $this->crewRangeToUtc($data['starts_at'], $data['ends_at']);
        $data['timezone'] = app(Timezone::class)->current();
        $this->performCrewAction(function () use ($data): void {
            if ($this->editingShiftId !== null) {
                $shift = $this->assignment->shifts()->findOrFail($this->editingShiftId);
                $data += $shift->only(['location_name', 'notes']);
                app(UpdateCrewShift::class)($shift, UpdateCrewShiftData::from($data));
            } else {
                app(CreateCrewShift::class)($this->assignment, CreateCrewShiftData::from($data));
            }
            $this->reset('editingShiftId', 'starts_at', 'ends_at', 'type', 'break_minutes');
            unset($this->shifts);
        });
    }

    public function editShift(int $id): void
    {
        $shift = $this->assignment->shifts()->findOrFail($id);
        Gate::authorize('update', $shift);
        $this->editingShiftId = $shift->id;
        $this->starts_at = app(Timezone::class)->toLocal($shift->starts_at)->format('Y-m-d\TH:i');
        $this->ends_at = app(Timezone::class)->toLocal($shift->ends_at)->format('Y-m-d\TH:i');
        $this->type = $shift->type->value;
        $this->break_minutes = $shift->break_minutes;
    }

    public function cancelShiftEdit(): void
    {
        $this->reset('editingShiftId', 'starts_at', 'ends_at', 'type', 'break_minutes');
        $this->resetValidation();
    }

    public function cancelShift(int $id): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->performCrewAction(function () use ($id): void {
            app(CancelCrewShift::class)($this->assignment->shifts()->findOrFail($id), CancelCrewShiftData::from(['reason' => $this->reason]));
            unset($this->shifts);
        });
    }

    public function confirmShift(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(ConfirmCrewShift::class)($this->assignment->shifts()->findOrFail($id));
            unset($this->shifts);
        });
    }

    public function startShift(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(StartCrewShift::class)($this->assignment->shifts()->findOrFail($id));
            unset($this->shifts);
        });
    }

    public function completeShift(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(CompleteCrewShift::class)($this->assignment->shifts()->findOrFail($id));
            unset($this->shifts);
        });
    }

    public function createTimesheet(): void
    {
        $data = $this->validate(['period_start' => ['required', 'date_format:Y-m-d'], 'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start']]);
        $this->performCrewAction(function () use ($data): void {
            $timesheet = app(CreateCrewTimesheet::class)($this->assignment, CreateCrewTimesheetData::from($data));
            $this->redirectRoute('crew.timesheets.show', ['crewTimesheet' => $timesheet->id], navigate: true);
        });
    }

    public function render(): View
    {
        return view('crew::assignments.show');
    }
}

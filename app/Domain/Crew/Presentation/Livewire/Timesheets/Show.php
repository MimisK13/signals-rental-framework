<?php

declare(strict_types=1);

namespace App\Domain\Crew\Presentation\Livewire\Timesheets;

use App\Domain\Crew\Actions\Timesheets\AddCrewTimeEntry;
use App\Domain\Crew\Actions\Timesheets\ApproveCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\LockCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\RejectCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\RemoveCrewTimeEntry;
use App\Domain\Crew\Actions\Timesheets\SubmitCrewTimesheet;
use App\Domain\Crew\Actions\Timesheets\UpdateCrewTimeEntry;
use App\Domain\Crew\Data\Timesheets\AddCrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\ApproveCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\RejectCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\UpdateCrewTimeEntryData;
use App\Domain\Crew\Enums\CrewTimeEntryKind;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Presentation\Livewire\CrewComponent;
use App\Support\Timezone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;

/**
 * @property-read CrewTimesheet $timesheet
 */
#[Layout('components.layouts.app')]
#[Title('Crew timesheet')]
final class Show extends CrewComponent
{
    #[Locked]
    public int $timesheetId;

    #[Locked]
    public int $version;

    #[Locked]
    public ?int $editingEntryId = null;

    public string $started_at = '';

    public string $ended_at = '';

    public string $kind = 'work';

    public int $break_minutes = 0;

    public string $reason = '';

    public function mount(CrewTimesheet $crewTimesheet): void
    {
        Gate::authorize('view', $crewTimesheet);
        $this->timesheetId = $crewTimesheet->id;
        $this->version = $crewTimesheet->version;
    }

    #[Computed]
    public function timesheet(): CrewTimesheet
    {
        $timesheet = CrewTimesheet::with(['crewMember', 'project', 'entries'])->findOrFail($this->timesheetId);
        Gate::authorize('view', $timesheet);

        return $timesheet;
    }

    protected function refreshTimesheet(): void
    {
        unset($this->timesheet);
        $this->version = $this->timesheet->version;
    }

    public function addEntry(): void
    {
        $data = $this->validate(['started_at' => ['required', 'date'], 'ended_at' => ['required', 'date', 'after:started_at'], 'kind' => ['required', Rule::enum(CrewTimeEntryKind::class)], 'break_minutes' => ['required', 'integer', 'min:0']]);
        [$data['started_at'], $data['ended_at']] = $this->crewRangeToUtc($data['started_at'], $data['ended_at'], 'started_at', 'ended_at');
        $data['version'] = $this->version;
        $this->performCrewAction(function () use ($data): void {
            if ($this->editingEntryId !== null) {
                $entry = $this->timesheet->entries()->findOrFail($this->editingEntryId);
                $data += $entry->only(['crew_shift_id', 'notes']);
                app(UpdateCrewTimeEntry::class)($entry, UpdateCrewTimeEntryData::from($data));
            } else {
                app(AddCrewTimeEntry::class)($this->timesheet, AddCrewTimeEntryData::from($data));
            }
            $this->reset('editingEntryId', 'started_at', 'ended_at', 'kind', 'break_minutes');
            $this->refreshTimesheet();
        });
    }

    public function editEntry(int $id): void
    {
        Gate::authorize('update', $this->timesheet);
        $entry = $this->timesheet->entries()->findOrFail($id);
        $this->editingEntryId = $entry->id;
        $this->started_at = app(Timezone::class)->toLocal($entry->started_at)->format('Y-m-d\TH:i');
        $this->ended_at = app(Timezone::class)->toLocal($entry->ended_at)->format('Y-m-d\TH:i');
        $this->kind = $entry->kind->value;
        $this->break_minutes = $entry->break_minutes;
    }

    public function cancelEntryEdit(): void
    {
        $this->reset('editingEntryId', 'started_at', 'ended_at', 'kind', 'break_minutes');
        $this->resetValidation();
    }

    public function removeEntry(int $id): void
    {
        $this->performCrewAction(function () use ($id): void {
            app(RemoveCrewTimeEntry::class)($this->timesheet->entries()->findOrFail($id), $this->version);
            $this->refreshTimesheet();
        });
    }

    public function submit(): void
    {
        $this->performCrewAction(function (): void {
            app(SubmitCrewTimesheet::class)($this->timesheet, $this->version);
            $this->refreshTimesheet();
        });
    }

    public function approve(): void
    {
        $this->performCrewAction(function (): void {
            app(ApproveCrewTimesheet::class)($this->timesheet, ApproveCrewTimesheetData::from(['version' => $this->version]));
            $this->refreshTimesheet();
        });
    }

    public function reject(): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->performCrewAction(function (): void {
            app(RejectCrewTimesheet::class)($this->timesheet, RejectCrewTimesheetData::from(['reason' => $this->reason, 'version' => $this->version]));
            $this->refreshTimesheet();
        });
    }

    public function lock(): void
    {
        $this->performCrewAction(function (): void {
            app(LockCrewTimesheet::class)($this->timesheet, $this->version);
            $this->refreshTimesheet();
        });
    }

    public function render(): View
    {
        return view('crew::timesheets.show');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SubmitCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, ?int $version = null): CrewTimesheetData
    {
        Gate::authorize('submit', $sheet);

        return DB::transaction(function () use ($sheet, $version): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'submit', $version);
            if (! $sheet->entries()->exists()) {
                throw ValidationException::withMessages(['entries' => 'Add at least one time entry before submitting.']);
            }
            $this->editor->assertChronological($sheet);
            $this->editor->recalculate($sheet);
            $sheet->submitted_by = auth()->id();
            $sheet->fill(['status' => CrewTimesheetStatus::Submitted, 'submitted_at' => now()]);
            $this->editor->save($sheet, 'crew.timesheet.submitted');

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

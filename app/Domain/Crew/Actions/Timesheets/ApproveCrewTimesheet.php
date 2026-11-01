<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\ApproveCrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, ?ApproveCrewTimesheetData $data = null): CrewTimesheetData
    {
        Gate::authorize('approve', $sheet);

        return DB::transaction(function () use ($sheet, $data): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'approve', $data?->version);
            if (! $sheet->entries()->exists()) {
                throw ValidationException::withMessages(['entries' => 'An empty timesheet cannot be approved.']);
            }
            $this->editor->recalculate($sheet);
            $sheet->fill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
            $this->editor->save($sheet, 'crew.timesheet.approved', ['total_pay_minor' => $sheet->total_pay_minor, 'currency_code' => $sheet->currency_code]);

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

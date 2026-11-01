<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Data\Timesheets\RejectCrewTimesheetData;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RejectCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, RejectCrewTimesheetData $data): CrewTimesheetData
    {
        Gate::authorize('reject', $sheet);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:10000']])->validate();

        return DB::transaction(function () use ($sheet, $data): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'reject', $data->version);
            $this->editor->assertChronological($sheet);
            $sheet->fill(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => auth()->id(), 'rejection_reason' => $data->reason]);
            $this->editor->save($sheet, 'crew.timesheet.rejected', ['reason' => $data->reason]);

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

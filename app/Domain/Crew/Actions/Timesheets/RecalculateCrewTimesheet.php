<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RecalculateCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, ?int $version = null): CrewTimesheetData
    {
        Gate::authorize('update', $sheet);

        return DB::transaction(function () use ($sheet, $version): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'update', $version);
            $this->editor->recalculate($sheet);
            $this->editor->save($sheet, 'crew.timesheet.recalculated');

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

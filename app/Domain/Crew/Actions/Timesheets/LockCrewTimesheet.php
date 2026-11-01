<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimesheetData;
use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class LockCrewTimesheet
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, ?int $version = null): CrewTimesheetData
    {
        Gate::authorize('lock', $sheet);

        return DB::transaction(function () use ($sheet, $version): CrewTimesheetData {
            $sheet = $this->editor->lock($sheet, 'lock', $version);

            $sheet->fill(['status' => CrewTimesheetStatus::Locked, 'locked_at' => now()]);
            $this->editor->save($sheet, 'crew.timesheet.locked');

            return CrewTimesheetData::fromModel($sheet);
        });
    }
}

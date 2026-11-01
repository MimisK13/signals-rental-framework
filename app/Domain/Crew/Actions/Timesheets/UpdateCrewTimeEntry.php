<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\CrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\UpdateCrewTimeEntryData;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateCrewTimeEntry
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimeEntry $entry, UpdateCrewTimeEntryData $data): CrewTimeEntryData
    {
        $sheet = $entry->timesheet;
        Gate::authorize('update', $sheet);

        return DB::transaction(function () use ($sheet, $data, $entry): CrewTimeEntryData {
            $sheet = $this->editor->lock($sheet, 'update', $data->version);
            $entry = $sheet->entries()->lockForUpdate()->findOrFail($entry->id);
            $entry = $this->editor->saveEntry($sheet, $data->toArray(), $entry);
            $this->editor->recalculate($sheet);
            $this->editor->save($sheet, 'crew.time_entry.updated', ['crew_time_entry_id' => $entry->id]);

            return CrewTimeEntryData::fromModel($entry->fresh());
        });
    }
}

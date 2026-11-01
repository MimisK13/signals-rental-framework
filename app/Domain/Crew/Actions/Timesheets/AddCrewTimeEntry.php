<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Data\Timesheets\AddCrewTimeEntryData;
use App\Domain\Crew\Data\Timesheets\CrewTimeEntryData;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AddCrewTimeEntry
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimesheet $sheet, AddCrewTimeEntryData $data): CrewTimeEntryData
    {

        Gate::authorize('update', $sheet);

        return DB::transaction(function () use ($sheet, $data): CrewTimeEntryData {
            $sheet = $this->editor->lock($sheet, 'update', $data->version);

            $entry = $this->editor->saveEntry($sheet, $data->toArray());
            $this->editor->recalculate($sheet);
            $this->editor->save($sheet, 'crew.time_entry.created', ['crew_time_entry_id' => $entry->id]);

            return CrewTimeEntryData::fromModel($entry->fresh());
        });
    }
}

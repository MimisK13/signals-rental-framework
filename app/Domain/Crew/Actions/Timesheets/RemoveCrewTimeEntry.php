<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Timesheets;

use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Services\CrewTimesheetEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class RemoveCrewTimeEntry
{
    public function __construct(private CrewTimesheetEditor $editor) {}

    public function __invoke(CrewTimeEntry $entry, ?int $version = null): void
    {
        $sheet = $entry->timesheet;
        Gate::authorize('update', $sheet);
        DB::transaction(function () use ($sheet, $entry, $version): void {
            $sheet = $this->editor->lock($sheet, 'update', $version);
            $entry = $sheet->entries()->lockForUpdate()->findOrFail($entry->id);
            $entry->delete();
            $this->editor->recalculate($sheet);
            $this->editor->save($sheet, 'crew.time_entry.deleted', ['crew_time_entry_id' => $entry->id]);
        });
    }
}

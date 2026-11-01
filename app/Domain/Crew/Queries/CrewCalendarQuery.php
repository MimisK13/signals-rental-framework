<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Planning\CrewCalendarFilterData;
use App\Domain\Crew\Data\Planning\CrewCalendarRowData;
use App\Domain\Crew\Data\Shifts\CrewShiftData;
use App\Domain\Crew\Models\CrewShift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class CrewCalendarQuery
{
    /** @return Collection<int,CrewCalendarRowData> */
    public function forWindow(CrewCalendarFilterData $filter): Collection
    {
        Gate::authorize('viewAny', CrewShift::class);
        [$start,$end] = StaffingGuard::dates($filter->starts_at, $filter->ends_at);
        StaffingGuard::require($start->diffInDays($end) <= 93, 'ends_at', 'Calendar windows cannot exceed 93 days.');

        return CrewShift::query()->with(['crewMember' => function ($query): void {
            $query->withTrashed();
        }])->whereNotNull('crew_member_id')->where('status', '!=', 'cancelled')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($filter->crew_member_id, function ($query) use ($filter): void {
                $query->where('crew_member_id', $filter->crew_member_id);
            })
            ->when($filter->project_id, function ($query) use ($filter): void {
                $query->where('project_id', $filter->project_id);
            })
            ->orderBy('starts_at')->get()->groupBy('crew_member_id')->map(function ($shifts): CrewCalendarRowData {
                return new CrewCalendarRowData($shifts->first()->crew_member_id, $shifts->first()->crewMember->name, $shifts->map(function ($shift): CrewShiftData {
                    return CrewShiftData::fromModel($shift);
                })->all());
            })->values();
    }
}

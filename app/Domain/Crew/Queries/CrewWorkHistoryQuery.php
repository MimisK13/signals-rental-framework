<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Planning\CrewWorkHistoryData;
use App\Domain\Crew\Data\Planning\WorkHistoryFilterData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use Illuminate\Support\Facades\Gate;

final class CrewWorkHistoryQuery
{
    public function forMember(int $crewMemberId, WorkHistoryFilterData $filter): CrewWorkHistoryData
    {
        Gate::authorize('crew.reports.view');
        Gate::authorize('view', CrewMember::withTrashed()->findOrFail($crewMemberId));
        [$start,$end] = StaffingGuard::dates($filter->starts_at, $filter->ends_at);
        StaffingGuard::require($start->diffInDays($end) <= 3660, 'ends_at', 'History periods cannot exceed ten years.');
        $assignments = CrewAssignment::query()->with(['request', 'line.role', 'timesheets' => function ($query) use ($start, $end): void {
            $query->whereIn('status', ['approved', 'locked'])->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start);
        }])->where('crew_member_id', $crewMemberId)->whereHas('line', function ($query) use ($start, $end): void {
            $query->whereRaw('COALESCE(crew_request_lines.starts_at, (SELECT starts_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) < ?', [$end])
                ->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [$start]);
        })->orderByDesc('created_at')->limit(500)->get();
        $rows = $assignments->map(function (CrewAssignment $assignment): array {
            return ['assignment_id' => $assignment->id, 'project_id' => $assignment->project_id, 'request_title' => $assignment->request->title, 'role' => $assignment->line->role->name,
                'status' => $assignment->status->value, 'starts_at' => $assignment->line->effectiveStartsAt()->toIso8601String(), 'ends_at' => $assignment->line->effectiveEndsAt()->toIso8601String(),
                'approved_minutes' => (int) $assignment->timesheets->sum('total_minutes')];
        })->all();

        return new CrewWorkHistoryData($crewMemberId, $rows);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CrewConflictReportData;
use App\Domain\Crew\Enums\CrewMemberStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Stores\Scopes\StoreScope;
use Carbon\CarbonImmutable;

final class CrewConflictQuery
{
    public function detect(int $crewMemberId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $ignoreShiftId = null, ?int $ignoreAssignmentId = null): CrewConflictReportData
    {
        $report = new CrewConflictReportData;
        $member = CrewMember::withTrashed()->findOrFail($crewMemberId);
        if ($member->trashed() || $member->status !== CrewMemberStatus::Active) {
            $report->hard[] = ['code' => 'MEMBER_INACTIVE', 'message' => 'The crew member is not active.'];
        }
        if (($member->starts_on && $startsAt->lt($member->starts_on->startOfDay())) || ($member->ends_on && $endsAt->gt($member->ends_on->copy()->addDay()->startOfDay()))) {
            $report->hard[] = ['code' => 'ENGAGEMENT_WINDOW', 'message' => 'The period falls outside the crew engagement dates.'];
        }
        $availability = CrewAvailability::query()->where('crew_member_id', $crewMemberId)->whereIn('status', ['approved', 'requested'])
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->get();
        foreach ($availability as $block) {
            if ($block->status->value === 'approved') {
                $report->hard[] = ['code' => 'LEAVE_APPROVED', 'message' => 'The crew member has approved unavailability during this period.'];
            } else {
                $report->warnings[] = ['code' => 'LEAVE_REQUESTED', 'message' => 'The crew member has requested unavailability during this period.'];
            }
        }
        $restHours = (float) settings('crew.min_rest_hours', 11);
        $shifts = CrewShift::withoutGlobalScope(StoreScope::class)->where('crew_member_id', $crewMemberId)
            ->whereIn('status', ['planned', 'confirmed', 'in_progress', 'completed'])
            ->when($ignoreShiftId !== null, function ($query) use ($ignoreShiftId): void {
                $query->whereKeyNot($ignoreShiftId);
            })
            ->when($ignoreAssignmentId !== null, function ($query) use ($ignoreAssignmentId): void {
                $query->where(function ($otherAssignment) use ($ignoreAssignmentId): void {
                    $otherAssignment->whereNull('crew_assignment_id')->orWhere('crew_assignment_id', '!=', $ignoreAssignmentId);
                });
            })
            ->where('starts_at', '<', $endsAt->addMinutes((int) ($restHours * 60)))
            ->where('ends_at', '>', $startsAt->subMinutes((int) ($restHours * 60)))->get();
        foreach ($shifts as $shift) {
            $overlap = $shift->starts_at->lt($endsAt) && $shift->ends_at->gt($startsAt);
            if ($overlap) {
                $conflict = ['code' => $shift->status->value === 'planned' ? 'SHIFT_OVERLAP_PLANNED' : 'SHIFT_OVERLAP_CONFIRMED', 'message' => 'The crew member has another shift during this period.'];
                if ($shift->status->value === 'planned') {
                    $report->warnings[] = $conflict;
                } else {
                    $report->hard[] = $conflict;
                }
            } elseif ($shift->status->value !== 'planned' && settings('crew.rest_enforcement', 'hard') !== 'off') {
                $gap = $shift->ends_at->lte($startsAt) ? $shift->ends_at->diffInMinutes($startsAt) : $endsAt->diffInMinutes($shift->starts_at);
                if ($gap < $restHours * 60) {
                    $conflict = ['code' => 'REST_BELOW_MINIMUM', 'message' => 'The interval between shifts is below the configured minimum rest.'];
                    if (settings('crew.rest_enforcement', 'warning') === 'hard') {
                        $report->hard[] = $conflict;
                    } else {
                        $report->warnings[] = $conflict;
                    }
                }
            }
        }
        $booked = CrewAssignment::withoutGlobalScope(StoreScope::class)->where('crew_member_id', $crewMemberId)->where('status', 'confirmed')
            ->when($ignoreAssignmentId !== null, function ($query) use ($ignoreAssignmentId): void {
                $query->whereKeyNot($ignoreAssignmentId);
            })
            ->whereDoesntHave('shifts', function ($query): void {
                $query->withoutGlobalScope(StoreScope::class)->whereIn('status', ['confirmed', 'in_progress', 'completed']);
            })
            ->whereHas('line', function ($query) use ($startsAt, $endsAt): void {
                $query->whereRaw('COALESCE(crew_request_lines.starts_at, (SELECT starts_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) < ?', [$endsAt])->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [$startsAt]);
            })->exists();
        if ($booked) {
            $report->hard[] = ['code' => 'DOUBLE_BOOKED_PROJECT', 'message' => 'The crew member has another confirmed assignment during this period.'];
        }

        return $report;
    }
}

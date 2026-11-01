<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Planning\CrewUtilisationData;
use App\Domain\Crew\Data\Planning\CrewUtilisationRowData;
use App\Domain\Crew\Data\Planning\UtilisationFilterData;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Crew\Models\CrewTimeEntry;
use Illuminate\Support\Facades\Gate;

final class CrewUtilisationQuery
{
    public function forPeriod(UtilisationFilterData $filter): CrewUtilisationData
    {
        Gate::authorize('crew.reports.view');
        [$start,$end] = StaffingGuard::dates($filter->starts_at, $filter->ends_at);
        StaffingGuard::require($start->diffInDays($end) <= 366, 'ends_at', 'Utilisation periods cannot exceed one year.');
        $members = CrewMember::query()->when($filter->crew_member_id, function ($query) use ($filter): void {
            $query->whereKey($filter->crew_member_id);
        })->orderBy('name')->limit(200)->get();
        $ids = $members->modelKeys();
        $shifts = CrewShift::query()->whereIn('crew_member_id', $ids)->whereIn('status', ['confirmed', 'in_progress', 'completed'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get()->groupBy('crew_member_id');
        $blocks = CrewAvailability::query()->whereIn('crew_member_id', $ids)->where('status', 'approved')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->get()->groupBy('crew_member_id');
        $entries = CrewTimeEntry::query()->with('timesheet:id,crew_member_id')->whereHas('timesheet', function ($query) use ($ids): void {
            $query->whereIn('crew_member_id', $ids)->whereIn('status', ['approved', 'locked']);
        })->where('started_at', '<', $end)->where('ended_at', '>', $start)->get()->groupBy(function ($entry): int {
            return $entry->timesheet->crew_member_id;
        });
        $rows = [];
        $minutes = function ($record, string $from, string $to) use ($start, $end): int {
            $seconds = max(0, min($end->timestamp, $record->{$to}->timestamp) - max($start->timestamp, $record->{$from}->timestamp));

            return max(0, intdiv($seconds, 60) - (int) ($record->break_minutes ?? 0));
        };
        foreach ($members as $member) {
            $busy = [];
            foreach ($blocks->get($member->id, collect()) as $block) {
                $busy[] = [max($start->timestamp, $block->starts_at->timestamp), min($end->timestamp, $block->ends_at->timestamp)];
            }
            usort($busy, function (array $a, array $b): int {
                return $a[0] <=> $b[0];
            });
            $unavailable = 0;
            $lastStart = null;
            $lastEnd = null;
            foreach ($busy as [$a,$b]) {
                if ($lastEnd !== null && $a <= $lastEnd) {
                    $lastEnd = max($lastEnd, $b);
                } else {
                    if ($lastEnd !== null) {
                        $unavailable += $lastEnd - $lastStart;
                    } $lastStart = $a;
                    $lastEnd = $b;
                }
            }
            if ($lastEnd !== null) {
                $unavailable += $lastEnd - $lastStart;
            }
            $capacity = max(0, intdiv($end->timestamp - $start->timestamp - $unavailable, 60));
            $booked = $shifts->get($member->id, collect())->sum(function ($shift) use ($minutes): int {
                return $minutes($shift, 'starts_at', 'ends_at');
            });
            $worked = $entries->get($member->id, collect())->sum(function ($entry) use ($minutes): int {
                return $minutes($entry, 'started_at', 'ended_at');
            });
            $rows[] = new CrewUtilisationRowData($member->id, $member->name, $capacity, $booked, $worked, $capacity > 0 ? round($booked / $capacity * 100, 2) : 0.0);
        }

        return new CrewUtilisationData($rows);
    }
}

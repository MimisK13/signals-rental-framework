<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Actions\Concerns\StaffingGuard;
use App\Domain\Crew\Data\Planning\CrewAvailabilityWindowData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewShift;
use App\Models\Scopes\StoreScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

final class CrewAvailabilityQuery
{
    public function windowsFor(int $crewMemberId, CarbonImmutable $from, CarbonImmutable $to): CrewAvailabilityWindowData
    {
        Gate::authorize('crew.availability.view');

        StaffingGuard::require($from->lt($to), 'ends_at', 'The end must follow the start.');

        $intervals = [];

        $blocks = CrewAvailability::where('crew_member_id', $crewMemberId)->where('status', 'approved')->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();
        $shifts = CrewShift::withoutGlobalScope(StoreScope::class)->where('crew_member_id', $crewMemberId)->whereIn('status', ['confirmed', 'in_progress', 'completed'])->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();

        foreach ($blocks->concat($shifts) as $block) {
            $intervals[] = [max($from->timestamp, $block->starts_at->timestamp), min($to->timestamp, $block->ends_at->timestamp)];
        }

        $assignments = CrewAssignment::withoutGlobalScope(StoreScope::class)->with('line')->where('crew_member_id', $crewMemberId)->where('status', 'confirmed')->whereDoesntHave('shifts', function ($query): void {
            $query->withoutGlobalScope(StoreScope::class)->whereIn('status', ['confirmed', 'in_progress', 'completed']);
        })->whereHas('line', function ($query) use ($from, $to): void {
            $query->whereRaw('COALESCE(crew_request_lines.starts_at, (SELECT starts_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) < ?', [$to])->whereRaw('COALESCE(crew_request_lines.ends_at, (SELECT ends_at FROM crew_requests WHERE crew_requests.id = crew_request_lines.crew_request_id)) > ?', [$from]);
        })->get();

        foreach ($assignments as $assignment) {
            $intervals[] = [max($from->timestamp, $assignment->line->effectiveStartsAt()->timestamp), min($to->timestamp, $assignment->line->effectiveEndsAt()->timestamp)];
        }

        usort($intervals, function (array $a, array $b): int {
            return $a[0] <=> $b[0];
        });

        $merged = [];

        foreach ($intervals as $interval) {
            $last = count($merged) - 1;
            if ($last >= 0 && $interval[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $interval[1]);
            } else {
                $merged[] = $interval;
            }
        }

        $free = [];
        $cursor = $from->timestamp;

        foreach ($merged as $interval) {
            if ($cursor < $interval[0]) {
                $free[] = [$cursor, $interval[0]];
            } $cursor = max($cursor, $interval[1]);
        }

        if ($cursor < $to->timestamp) {
            $free[] = [$cursor, $to->timestamp];
        }

        $format = function (array $interval): array {
            return [
                'starts_at' => CarbonImmutable::createFromTimestampUTC($interval[0])->toIso8601String(),
                'ends_at' => CarbonImmutable::createFromTimestampUTC($interval[1])->toIso8601String(),
            ];
        };

        return new CrewAvailabilityWindowData($crewMemberId, array_map($format, $merged), array_map($format, $free));
    }
}

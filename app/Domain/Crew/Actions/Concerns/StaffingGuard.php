<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Concerns;

use App\Domain\Crew\Data\Planning\CrewConflictReportData;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Services\CrewCompensationCalculator;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StaffingGuard
{
    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function dates(string $start, string $end, string $timezone = 'UTC'): array
    {
        Validator::make(['starts_at' => $start, 'ends_at' => $end, 'timezone' => $timezone], [
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'timezone' => ['required', 'timezone'],
        ])->validate();

        $from = self::instant($start, $timezone, 'starts_at');
        $to = self::instant($end, $timezone, 'ends_at');
        self::require($to->gt($from), 'ends_at', 'The end must follow the start.');

        return [$from, $to];
    }

    private static function instant(string $value, string $timezone, string $field): CarbonImmutable
    {
        if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value)) {
            return CarbonImmutable::parse($value)->utc();
        }

        self::require((bool) preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?)?$/', $value, $parts), $field, 'Enter an ISO date or date and time with an optional UTC offset.');
        $wall = $parts[1].' '.($parts[2] ?? '00').':'.($parts[3] ?? '00').':'.($parts[4] ?? '00');
        $local = CarbonImmutable::parse($value, $timezone);
        self::require($local->format('Y-m-d H:i:s') === $wall, $field, 'This local time does not exist on this date.');

        // Both offsets can map an autumn wall time to a valid instant. Pick the earlier one.
        $naive = CarbonImmutable::parse($wall, 'UTC')->timestamp;
        $transitions = (new DateTimeZone($timezone))->getTransitions($naive - 86400, $naive + 86400);
        $instants = [];
        foreach ($transitions ?: [] as $transition) {
            $candidate = CarbonImmutable::createFromTimestampUTC($naive - $transition['offset'])->setMicrosecond($local->microsecond);
            if ($candidate->timezone($timezone)->format('Y-m-d H:i:s') === $wall) {
                $instants[] = $candidate;
            }
        }
        usort($instants, static function (CarbonImmutable $a, CarbonImmutable $b): int {
            return $a->timestamp <=> $b->timestamp;
        });

        return $instants[0] ?? $local->utc();
    }

    public static function require(bool $condition, string $key, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    public static function memberLock(int $id): CrewMember
    {
        return CrewMember::query()->lockForUpdate()->findOrFail($id);
    }

    public static function estimate(CrewAssignment $assignment): void
    {
        if (isset($assignment->cost_estimate_breakdown['cancellation'])) {
            return;
        }
        $shifts = $assignment->shifts()->where('status', '!=', 'cancelled')->orderBy('starts_at')->get();
        if ($shifts->isEmpty()) {
            $assignment->update(['cost_estimate_minor' => null, 'cost_estimate_breakdown' => null]);

            return;
        }
        $entries = $shifts->map(static function ($shift): array {
            return ['id' => $shift->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at,
                'timezone' => $shift->timezone, 'break_minutes' => $shift->break_minutes,
                'kind' => in_array($shift->type->value, ['travel', 'standby'], true) ? $shift->type->value : 'work'];
        });
        $result = app(CrewCompensationCalculator::class)->calculate($assignment, $entries);
        $assignment->update(['cost_estimate_minor' => $result['total_pay_minor'], 'cost_estimate_breakdown' => $result['breakdown']]);
    }

    public static function editable(CrewRequest $request): void
    {
        self::require(in_array($request->status, [CrewRequestStatus::Draft, CrewRequestStatus::Open], true), 'status', 'Only draft or open requests may be edited.');
    }

    public static function conflicts(CrewConflictReportData $report, ?string $overrideReason = null): void
    {
        self::require(! $report->hasHardConflicts(), 'crew_member_id', implode(' ', array_column($report->hard, 'message')));
        if ($report->warnings !== []) {
            Gate::authorize('crew.conflicts.override');
            self::require((bool) settings('crew.allow_conflict_override', true), 'override_reason', 'Scheduling warning overrides are disabled.');
            self::require($overrideReason !== null && trim($overrideReason) !== '', 'override_reason', 'A reason is required to acknowledge scheduling warnings.');
        }
    }
}

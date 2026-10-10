<?php

namespace App\Services\Assets;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetOwnershipType;
use App\Enums\StockMethod;
use App\Enums\UtilizationCompleteness;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetUsage;
use App\Models\Repair;
use App\Support\Intervals\TimeInterval;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AssetUtilizationCalculator
{
    public function calculate(Asset $asset, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): AssetUtilizationResult
    {
        $this->assertWindow($from, $until);
        $from = $from->utc();
        $until = $until->utc();
        $asOf = $asOf->utc();

        $asset->loadMissing(['product', 'usages', 'repairs', 'lifecycleEvents']);

        $usageIntervals = $this->usageIntervals($asset->usages, $from, $until, $asOf);
        $repairIntervals = $this->repairIntervals($asset->repairs, $from, $until, $asOf);
        $lifecycle = $this->lifecycleIntervals($asset, $from, $until, $asOf);
        $reasons = $lifecycle['reasons'];

        $actualRentalDuration = TimeInterval::totalSeconds($usageIntervals['intervals']);
        $maintenanceDowntime = TimeInterval::totalSeconds($repairIntervals);
        $lifecycleDowntime = TimeInterval::totalSeconds($lifecycle['blocking']);

        $participationCount = $asset->usages->filter(function (AssetUsage $usage) use ($from, $until): bool {
            if ($usage->getRawOriginal('dispatched_at') === null) {
                return false;
            }

            $start = CarbonImmutable::parse($usage->dispatched_at)->utc();
            $end = $usage->returned_at ? CarbonImmutable::parse($usage->returned_at)->utc() : null;

            return $end === null || ($start->lessThan($until) && $end->greaterThan($from));
        })->count();

        $eligible = $this->isEligible($asset);
        if (! $eligible) {
            return $this->unavailableResult(
                $from,
                $until,
                $asOf,
                $actualRentalDuration,
                $maintenanceDowntime,
                $lifecycleDowntime,
                $participationCount,
                ['ownership_ineligible'],
            );
        }

        $owned = $this->ownedInterval($asset, $from, $until);
        if ($owned === null) {
            $reason = $asset->getRawOriginal('acquired_at') === null
                ? 'acquisition_unknown'
                : 'no_plannable_time';

            return $this->unavailableResult(
                $from,
                $until,
                $asOf,
                $actualRentalDuration,
                $maintenanceDowntime,
                $lifecycleDowntime,
                $participationCount,
                [$reason],
            );
        }

        $operational = $this->operationalInterval($asset, $owned);
        $lifecycleBlockedIntervals = [];
        foreach ($lifecycle['blocking'] as $interval) {
            $intersection = $interval->intersection($operational);
            if ($intersection !== null) {
                $lifecycleBlockedIntervals[] = $intersection;
            }
        }
        $lifecycleBlocked = TimeInterval::union($lifecycleBlockedIntervals);
        $maintenanceBlocked = $this->intersectAndSubtract($repairIntervals, $operational, $lifecycleBlocked);
        $plannable = $this->subtractIntervals([$operational], [...$lifecycleBlocked, ...$maintenanceBlocked]);
        $plannableDuration = TimeInterval::totalSeconds($plannable);
        $rentalWithinPlannable = $this->intersectIntervals($usageIntervals['intervals'], $plannable);
        $rentalWithinPlannableDuration = TimeInterval::totalSeconds($rentalWithinPlannable);
        $idle = $this->subtractIntervals($plannable, $usageIntervals['intervals']);
        $idleDuration = TimeInterval::totalSeconds($idle);
        $operationalDuration = $operational->seconds();
        $ownedDuration = $owned->seconds();

        if ($lifecycle['incomplete']) {
            $reasons[] = 'lifecycle_history_incomplete';
        }

        if ($usageIntervals['incomplete']) {
            $reasons[] = 'usage_incomplete';
        }

        if ($usageIntervals['overlap']) {
            $reasons[] = 'overlapping_usage';
        }

        $reasons = array_values(array_unique($reasons));
        $complete = $reasons === [];
        $completeness = $complete ? UtilizationCompleteness::Complete : UtilizationCompleteness::Partial;
        $denominatorValid = $plannableDuration > 0 && $operationalDuration > 0;

        if (! $denominatorValid) {
            $reasons[] = 'no_plannable_time';
            $completeness = UtilizationCompleteness::Unavailable;
        }

        $ratiosAvailable = $complete && $denominatorValid;

        return new AssetUtilizationResult(
            windowStart: $from,
            windowEnd: $until,
            asOf: $asOf,
            ownedDuration: $ownedDuration,
            operationalLifetime: $operationalDuration,
            plannableDuration: $plannableDuration,
            actualRentalDuration: $actualRentalDuration,
            rentalWithinPlannableDuration: $ratiosAvailable ? $rentalWithinPlannableDuration : null,
            rentalParticipationCount: $participationCount,
            maintenanceDowntime: $maintenanceDowntime,
            maintenanceBlockedDuration: $ratiosAvailable ? TimeInterval::totalSeconds($maintenanceBlocked) : null,
            lifecycleDowntime: $lifecycleDowntime,
            lifecycleBlockedDuration: $ratiosAvailable ? TimeInterval::totalSeconds($lifecycleBlocked) : null,
            idlePlannableDuration: $ratiosAvailable ? $idleDuration : null,
            actualRentalUtilizationRatio: $ratiosAvailable ? $this->ratio($rentalWithinPlannableDuration, $plannableDuration) : null,
            calendarRentalUtilizationRatio: $ratiosAvailable ? $this->ratio($actualRentalDuration, $operationalDuration) : null,
            maintenanceDowntimeRatio: $ratiosAvailable ? $this->ratio(TimeInterval::totalSeconds($maintenanceBlocked), $operationalDuration) : null,
            lifecycleDowntimeRatio: $ratiosAvailable ? $this->ratio(TimeInterval::totalSeconds($lifecycleBlocked), $operationalDuration) : null,
            idleRatio: $ratiosAvailable ? $this->ratio($idleDuration, $plannableDuration) : null,
            completeness: $completeness,
            unavailableReasons: array_values(array_unique($reasons)),
        );
    }

    private function assertWindow(CarbonImmutable $from, CarbonImmutable $until): void
    {
        if ($from->greaterThanOrEqualTo($until)) {
            throw new InvalidArgumentException('The utilization window must end after it starts.');
        }
    }

    private function isEligible(Asset $asset): bool
    {
        return $asset->product?->stock_method === StockMethod::Serialised
            && AssetOwnershipType::tryFrom((string) $asset->getRawOriginal('ownership_type')) === AssetOwnershipType::CompanyOwned;
    }

    private function ownedInterval(Asset $asset, CarbonImmutable $from, CarbonImmutable $until): ?TimeInterval
    {
        if ($asset->acquired_at === null) {
            return null;
        }

        $start = CarbonImmutable::parse($asset->acquired_at)->utc();
        $end = $this->terminalOwnershipEnd($asset) ?? $until;

        if ($start->greaterThanOrEqualTo($end)) {
            return null;
        }

        return (new TimeInterval($start, $end))->clippedTo($from, $until);
    }

    private function operationalInterval(Asset $asset, TimeInterval $owned): TimeInterval
    {
        $retirementEvent = $asset->lifecycleEvents
            ->filter(fn (AssetLifecycleEvent $event): bool => $event->getRawOriginal('new_status') === AssetLifecycleStatus::Retired->value)
            ->sortBy('occurred_at')
            ->first();

        $retiredAt = $retirementEvent === null
            ? null
            : CarbonImmutable::parse((string) $retirementEvent->getRawOriginal('occurred_at'))->utc();

        if ($retiredAt === null) {
            return $owned;
        }

        $end = $retiredAt;

        $boundedEnd = $end->lessThan($owned->end) ? $end : $owned->end;

        return $owned->start->lessThan($boundedEnd)
            ? new TimeInterval($owned->start, $boundedEnd)
            : $owned;
    }

    private function terminalOwnershipEnd(Asset $asset): ?CarbonImmutable
    {
        $event = $asset->lifecycleEvents
            ->filter(fn (AssetLifecycleEvent $event): bool => $event->hasTerminalDisposition())
            ->sortBy('occurred_at')
            ->first();

        return $event === null ? null : CarbonImmutable::parse((string) $event->getRawOriginal('occurred_at'))->utc();
    }

    /**
     * @param  Collection<int, AssetUsage>  $usages
     * @return array{intervals: list<TimeInterval>, incomplete: bool, overlap: bool}
     */
    private function usageIntervals(Collection $usages, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): array
    {
        $intervals = [];
        $incomplete = false;

        foreach ($usages as $usage) {
            if ($usage->getRawOriginal('dispatched_at') === null) {
                $incomplete = true;

                continue;
            }

            $start = CarbonImmutable::parse($usage->dispatched_at)->utc();
            $end = $usage->returned_at ? CarbonImmutable::parse($usage->returned_at)->utc() : min($asOf, $until);

            if ($end->lessThanOrEqualTo($start)) {
                $incomplete = true;

                continue;
            }

            $interval = (new TimeInterval($start, $end))->clippedTo($from, $until);
            if ($interval !== null) {
                $intervals[] = $interval;
            }
        }

        return [
            'intervals' => $intervals,
            'incomplete' => $incomplete,
            'overlap' => count($intervals) !== count(TimeInterval::union($intervals)),
        ];
    }

    /**
     * @param  Collection<int, Repair>  $repairs
     * @return list<TimeInterval>
     */
    private function repairIntervals(Collection $repairs, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): array
    {
        $intervals = [];

        foreach ($repairs as $repair) {
            if ($repair->is_usable || $repair->getRawOriginal('reported_at') === null) {
                continue;
            }

            $start = CarbonImmutable::parse($repair->started_at ?? $repair->reported_at)->utc();
            $end = $repair->completed_at ? CarbonImmutable::parse($repair->completed_at)->utc() : min($asOf, $until);

            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            $interval = (new TimeInterval($start, $end))->clippedTo($from, $until);
            if ($interval !== null) {
                $intervals[] = $interval;
            }
        }

        return TimeInterval::union($intervals);
    }

    /** @return array{blocking: list<TimeInterval>, incomplete: bool, reasons: list<string>} */
    private function lifecycleIntervals(Asset $asset, CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $asOf): array
    {
        $events = $asset->lifecycleEvents
            ->sortBy('occurred_at')
            ->values();
        $firstEventAt = $events->first()?->getRawOriginal('occurred_at');
        $inFuture = $from->greaterThanOrEqualTo($asOf);
        $incomplete = $events->isEmpty() ? ! $inFuture : $from->lessThan(CarbonImmutable::parse((string) $firstEventAt)->utc());
        $blocking = [];

        if ($events->isEmpty()) {
            if (! $incomplete && in_array($asset->lifecycleStatus(), [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true)) {
                $end = $asOf->lessThan($until) ? $asOf : $until;
                if ($from->lessThan($end)) {
                    $blocking[] = new TimeInterval($from, $end);
                }
            }

            return ['blocking' => $blocking, 'incomplete' => $incomplete, 'reasons' => []];
        }

        $baseline = AssetLifecycleStatus::tryFrom((string) ($events->first()->getRawOriginal('previous_status') ?? AssetLifecycleStatus::Active->value))
            ?? AssetLifecycleStatus::Active;
        $cursor = CarbonImmutable::parse((string) $firstEventAt)->utc();
        $state = $baseline;

        foreach ($events as $event) {
            $eventAt = CarbonImmutable::parse($event->occurred_at)->utc();
            if ($state !== AssetLifecycleStatus::Active && $eventAt->greaterThan($cursor)) {
                $interval = (new TimeInterval($cursor, $eventAt))->clippedTo($from, $until);
                if ($interval !== null && in_array($state, [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true)) {
                    $blocking[] = $interval;
                }
            }

            $state = AssetLifecycleStatus::tryFrom((string) $event->getRawOriginal('new_status'))
                ?? AssetLifecycleStatus::Active;
            $cursor = $eventAt;
        }

        if (in_array($state, [AssetLifecycleStatus::Missing, AssetLifecycleStatus::Lost], true)) {
            $end = $asOf->lessThan($until) ? $asOf : $until;
            if ($end->greaterThan($cursor)) {
                $interval = (new TimeInterval($cursor, $end))->clippedTo($from, $until);
                if ($interval !== null) {
                    $blocking[] = $interval;
                }
            }
        }

        return ['blocking' => TimeInterval::union($blocking), 'incomplete' => $incomplete, 'reasons' => []];
    }

    /**
     * @param  list<TimeInterval>  $intervals
     * @param  list<TimeInterval>  $containers
     * @return list<TimeInterval>
     */
    private function intersectIntervals(array $intervals, array $containers): array
    {
        $result = [];
        foreach ($intervals as $interval) {
            foreach ($containers as $container) {
                $intersection = $interval->intersection($container);
                if ($intersection !== null) {
                    $result[] = $intersection;
                }
            }
        }

        return TimeInterval::union($result);
    }

    /**
     * @param  list<TimeInterval>  $intervals
     * @param  list<TimeInterval>  $blocked
     * @return list<TimeInterval>
     */
    private function intersectAndSubtract(array $intervals, TimeInterval $container, array $blocked): array
    {
        return $this->subtractIntervals($this->intersectIntervals($intervals, [$container]), $blocked);
    }

    /**
     * @param  list<TimeInterval>  $intervals
     * @param  list<TimeInterval>  $subtrahends
     * @return list<TimeInterval>
     */
    private function subtractIntervals(array $intervals, array $subtrahends): array
    {
        $result = [];
        foreach (TimeInterval::union($intervals) as $interval) {
            $result = [...$result, ...$interval->subtract($subtrahends)];
        }

        return TimeInterval::union($result);
    }

    private function ratio(int $numerator, int $denominator): string
    {
        return BigDecimal::of((string) $numerator)
            ->dividedBy((string) $denominator, 10, RoundingMode::HALF_UP)
            ->toScale(10, RoundingMode::HALF_UP)
            ->__toString();
    }

    /** @param list<string> $reasons */
    private function unavailableResult(
        CarbonImmutable $from,
        CarbonImmutable $until,
        CarbonImmutable $asOf,
        int $actualRentalDuration,
        int $maintenanceDowntime,
        int $lifecycleDowntime,
        int $participationCount,
        array $reasons,
    ): AssetUtilizationResult {
        return new AssetUtilizationResult(
            windowStart: $from,
            windowEnd: $until,
            asOf: $asOf,
            ownedDuration: null,
            operationalLifetime: null,
            plannableDuration: null,
            actualRentalDuration: $actualRentalDuration,
            rentalWithinPlannableDuration: null,
            rentalParticipationCount: $participationCount,
            maintenanceDowntime: $maintenanceDowntime,
            maintenanceBlockedDuration: null,
            lifecycleDowntime: $lifecycleDowntime,
            lifecycleBlockedDuration: null,
            idlePlannableDuration: null,
            actualRentalUtilizationRatio: null,
            calendarRentalUtilizationRatio: null,
            maintenanceDowntimeRatio: null,
            lifecycleDowntimeRatio: null,
            idleRatio: null,
            completeness: UtilizationCompleteness::Unavailable,
            unavailableReasons: $reasons,
        );
    }
}

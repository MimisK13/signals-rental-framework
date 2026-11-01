<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRateType;
use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewTimeEntry;
use Carbon\CarbonImmutable;

/** Records an unworked contractual guarantee, separately from actual time and payment. */
final class CrewCancellationCompensation
{
    /**
     * The cancellation action must hold the assignment/member locks before recording.
     *
     * @return array<string, mixed>
     */
    public function record(CrewAssignment $assignment, ?CarbonImmutable $cancelledAt = null): array
    {
        $existing = $assignment->cost_estimate_breakdown['cancellation'] ?? null;
        if (is_array($existing)) {
            return $existing;
        }
        $entitlement = $this->snapshot($assignment, $cancelledAt);
        $assignment->update([
            'cost_estimate_minor' => $entitlement['amount_minor'],
            'cost_estimate_breakdown' => [
                'planned_estimate_minor' => $assignment->cost_estimate_minor,
                'planned_breakdown' => $assignment->cost_estimate_breakdown,
                'cancellation' => $entitlement,
            ],
        ]);

        return $entitlement;
    }

    /** @return array<string, mixed> */
    public function snapshot(CrewAssignment $assignment, ?CarbonImmutable $cancelledAt = null): array
    {
        $cancelledAt = ($cancelledAt ?? CarbonImmutable::instance(now()))->utc();
        $policy = $assignment->allowances_snapshot['_policy'] ?? settings()->group('crew');
        $noticeHours = (int) ($policy['cancellation_pay_hours'] ?? settings('crew.cancellation_pay_hours', 48));
        $minimumMinutes = (int) bcadd(bcmul($assignment->min_call_hours ?? '0', '60', 6), '0.5', 0);
        $ledger = [
            'kind' => 'cancellation_call_minimum', 'status' => 'not_due',
            'cancelled_at' => $cancelledAt->toIso8601String(), 'notice_hours' => $noticeHours,
            'currency_code' => $assignment->currency_code,
            'exchange_rate_to_base' => $assignment->exchange_rate_to_base,
            'minutes' => 0, 'amount_minor' => 0, 'reason' => 'outside_notice_window', 'calls' => [],
        ];
        if ($assignment->status !== CrewAssignmentStatus::Confirmed) {
            $ledger['reason'] = 'assignment_not_confirmed';

            return $ledger;
        }
        if ($noticeHours <= 0 || $minimumMinutes <= 0 || $assignment->rate_type === CrewRateType::Fixed) {
            $ledger['reason'] = $noticeHours <= 0 ? 'cancellation_pay_disabled' : 'no_agreed_call_minimum';

            return $ledger;
        }
        $shifts = $assignment->shifts()->orderBy('starts_at')->get();
        $workedDates = [];
        $calls = [];
        foreach ($shifts as $shift) {
            $start = CarbonImmutable::instance($shift->starts_at)->utc();
            $end = CarbonImmutable::instance($shift->ends_at)->utc();
            $date = $start->setTimezone($shift->timezone)->toDateString();
            if ($shift->status === CrewShiftStatus::Completed) {
                $workedDates[$date] = true;
            }
            if (! in_array($shift->status, [CrewShiftStatus::Planned, CrewShiftStatus::Confirmed], true) || $end <= $cancelledAt) {
                continue;
            }
            if (! isset($calls[$date])) {
                $calls[$date] = ['date' => $date, 'starts_at' => $start, 'ends_at' => $end, 'source' => 'shift'];
            } else {
                $calls[$date]['ends_at'] = $calls[$date]['ends_at']->max($end);
            }
        }
        // A confirmed assignment without resolved shifts still guarantees its one known call.
        if ($shifts->isEmpty()) {
            $assignment->loadMissing('line.request');
            $start = CarbonImmutable::instance($assignment->line->effectiveStartsAt())->utc();
            $end = CarbonImmutable::instance($assignment->line->effectiveEndsAt())->utc();
            if ($end > $cancelledAt) {
                $date = $start->setTimezone($assignment->line->request->timezone)->toDateString();
                $calls[$date] = ['date' => $date, 'starts_at' => $start, 'ends_at' => $end, 'source' => 'request_line'];
            }
        }
        $actualEntries = CrewTimeEntry::query()->whereHas('timesheet', function ($query) use ($assignment): void {
            $query->where('crew_assignment_id', $assignment->id);
        })->where(function ($query) use ($cancelledAt): void {
            $query->where('ended_at', '<=', $cancelledAt)->orWhereHas('timesheet', function ($sheet): void {
                $sheet->whereIn('status', ['approved', 'locked']);
            });
        })->get(['work_date']);
        foreach ($actualEntries as $entry) {
            $workedDates[$entry->work_date->toDateString()] = true;
        }
        $hourlyRate = $assignment->rate_amount_minor;
        if ($assignment->rate_type === CrewRateType::Daily) {
            $dailyMinutes = max(1, (int) bcadd(bcmul($assignment->daily_call_hours ?? (string) settings('crew.daily_call_hours', '10'), '60', 6), '0.5', 0));
            $hourlyRate = $this->divide((string) ($assignment->rate_amount_minor * 60), $dailyMinutes);
        }
        ksort($calls);
        foreach ($calls as $date => $call) {
            $withinNotice = $call['starts_at'] <= $cancelledAt->addHours($noticeHours);
            $eligible = $withinNotice && ! isset($workedDates[$date]);
            $minutes = $eligible ? $minimumMinutes : 0;
            $amount = $this->divide((string) ($minutes * $hourlyRate), 60);
            $ledger['calls'][] = [
                'local_date' => $date, 'starts_at' => $call['starts_at']->toIso8601String(),
                'ends_at' => $call['ends_at']->toIso8601String(), 'source' => $call['source'],
                'minutes' => $minutes, 'hourly_equivalent_minor' => $hourlyRate, 'amount_minor' => $amount,
                'reason' => isset($workedDates[$date]) ? 'actual_work_claims_call_minimum' : ($withinNotice ? 'within_notice_window' : 'outside_notice_window'),
            ];
            $ledger['minutes'] += $minutes;
            $ledger['amount_minor'] += $amount;
        }
        if ($ledger['minutes'] > 0) {
            $ledger['status'] = 'due';
            $ledger['reason'] = 'within_notice_window';
        } elseif ($calls !== [] && count(array_intersect_key($calls, $workedDates)) === count($calls)) {
            $ledger['reason'] = 'actual_work_claims_call_minimum';
        }

        return $ledger;
    }

    private function divide(string $numerator, int $denominator): int
    {
        return (int) bcadd(bcdiv($numerator, (string) $denominator, 6), '0.5', 0);
    }
}

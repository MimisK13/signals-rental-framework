<?php

declare(strict_types=1);

namespace App\Domain\Crew\Services;

use App\Domain\Crew\Enums\CrewRateType;
use App\Domain\Crew\Enums\CrewShiftType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Settings\CrewSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** Compensation uses elapsed UTC minutes and decimal integer arithmetic. */
final class CrewCompensationCalculator
{
    /**
     * @param  iterable<array<string,mixed>|Model>  $entries
     * @param  array<string,mixed>|null  $settings
     * @return array<string,mixed>
     */
    public function calculate(CrewAssignment $assignment, iterable $entries, ?array $settings = null): array
    {
        $snapshot = $assignment->allowances_snapshot ?? [];
        $policy = array_replace((new CrewSettings)->defaults(), $snapshot['_policy'] ?? settings()->group('crew'), $settings ?? []);
        $timezone = $assignment->request->timezone;
        $days = [];
        $entryResults = [];
        $nightDates = [];
        foreach ($entries as $key => $entry) {
            $get = static function (string $field, mixed $default = null) use ($entry): mixed {
                return $entry[$field] ?? $default;
            };
            $start = CarbonImmutable::parse($get('started_at', $get('starts_at')))->utc();
            $end = CarbonImmutable::parse($get('ended_at', $get('ends_at')))->utc();
            $elapsed = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
            $break = (int) $get('break_minutes', 0);
            if ($elapsed <= 0 || $break < 0 || $break >= $elapsed || $elapsed > 44640) {
                throw ValidationException::withMessages(['ended_at' => 'The time range must be positive, at most 31 days, and longer than its unpaid break.']);
            }
            $minutes = $this->roundMinutes($elapsed - $break, $policy['rounding_mode'], (int) $policy['rounding_increment_minutes']);
            $entryTimezone = $get('timezone', $timezone);
            $kind = $get('kind', $get('type', 'work'));
            if ($kind instanceof CrewShiftType) {
                $kind = in_array($kind->value, ['travel', 'standby'], true) ? $kind->value : 'work';
            }
            $kind = $kind instanceof \BackedEnum ? $kind->value : $kind;
            $id = (int) $get('id', $key);
            $entryResults[$id] = ['minutes' => $minutes, 'amount_minor' => 0, 'applied_rate_minor' => $kind === 'travel' && isset($policy['travel_rate_minor']) ? (int) $policy['travel_rate_minor'] : $assignment->rate_amount_minor, 'is_overtime' => false, 'multiplier' => '1.00'];
            // A scalar break has no position. Deduct it from the end of the entry.
            for ($minute = 0; $minute < $minutes; $minute++) {
                $local = $start->addMinutes($minute)->setTimezone($entryTimezone);
                $date = $local->toDateString();
                $clock = $local->format('H:i');
                $nightStart = $policy['night_window_start'];
                $nightEnd = $policy['night_window_end'];
                $night = $nightStart < $nightEnd ? ($clock >= $nightStart && $clock < $nightEnd) : ($clock >= $nightStart || $clock < $nightEnd);
                if ($night) {
                    $nightDates[$date] = true;
                }
                $week = $local->startOfWeek($policy['week_starts_on'] === 'sunday' ? 0 : 1)->toDateString();
                $days[$date]['week'] = $week;
                $days[$date]['tokens'][] = ['entry' => $id, 'kind' => $kind, 'night' => $night, 'weekend' => $local->isWeekend(), 'holiday' => in_array($date, $policy['holiday_dates'] ?? [], true)];
            }
        }
        ksort($days);
        $weeks = [];
        $groups = [];
        $totalMinutes = 0;
        $minimum = $assignment->rate_type === CrewRateType::Fixed ? 0 : $this->hoursToMinutes($assignment->min_call_hours ?? '0');
        $dailyCall = max(1, $this->hoursToMinutes($assignment->daily_call_hours ?? $policy['daily_call_hours']));
        $ot = $assignment->overtime_multiplier ?? $policy['daily_ot_multiplier'];
        foreach ($days as $date => $day) {
            $worked = count($day['tokens']);
            $uplift = max(0, $minimum - $worked);
            $tokens = $day['tokens'];
            for ($i = 0; $i < $uplift; $i++) {
                $tokens[] = ['entry' => null, 'kind' => 'work', 'night' => false, 'weekend' => $day['tokens'][0]['weekend'], 'holiday' => $day['tokens'][0]['holiday']];
            }
            $totalMinutes += count($tokens);
            $dailyEligible = 0;
            $dailyCount = 0;
            $week = $day['week'];
            $weeks[$week] ??= (int) ($policy['prior_week_minutes'][$week] ?? 0);
            $dailyEligible = (int) ($policy['prior_day_minutes'][$date] ?? 0);
            foreach ($tokens as $token) {
                $dailyCount++;
                $travel = $token['kind'] === 'travel';
                $earnsOt = ! $travel || $policy['travel_earns_overtime'];
                if ($earnsOt) {
                    $dailyEligible++;
                }
                $dailyOt = $earnsOt && $dailyEligible > (int) $policy['daily_overtime_threshold_hours'] * 60;
                $weeklyOt = ! $dailyOt && $weeks[$week] >= (int) $policy['weekly_overtime_threshold_hours'] * 60;
                if (! $dailyOt) {
                    $weeks[$week]++;
                }
                $multiplier = '1.00';
                $code = $token['entry'] === null ? 'CALL_MIN' : 'REGULAR';
                $multipliers = [];
                if ($earnsOt && ($dailyOt || $weeklyOt)) {
                    $multipliers[] = $dailyOt ? (string) $ot : (string) $policy['weekly_ot_multiplier'];
                    $code = $dailyOt ? 'DAILY_OT' : 'WEEKLY_OT';
                }
                if ($token['night']) {
                    $multipliers[] = (string) $policy['night_multiplier'];
                }
                if ($token['weekend']) {
                    $multipliers[] = (string) $policy['weekend_multiplier'];
                }
                if ($token['holiday']) {
                    $multipliers[] = (string) $policy['holiday_multiplier'];
                }
                foreach ($multipliers as $premium) {
                    $multiplier = $policy['stack_multipliers'] ? bcmul($multiplier, $premium, 6) : (bccomp($premium, $multiplier, 6) > 0 ? $premium : $multiplier);
                }
                $rate = $travel && isset($policy['travel_rate_minor']) ? (int) $policy['travel_rate_minor'] : $assignment->rate_amount_minor;
                if ($assignment->rate_type === CrewRateType::Daily) {
                    if ($dailyCount <= $dailyCall) {
                        continue;
                    }
                    $rate = $this->divide((string) ($assignment->rate_amount_minor * 60), $dailyCall);
                    $multiplier = (string) $ot;
                    $code = 'DAILY_EXCESS';
                }
                if ($assignment->rate_type === CrewRateType::Fixed) {
                    continue;
                }
                $groupKey = implode(':', [$date, $token['entry'] ?? 'minimum', $code, $rate, $multiplier]);
                if (! isset($groups[$groupKey])) {
                    $groups[$groupKey] = ['code' => $token['entry'] === null ? 'CALL_MIN' : $code, 'date' => $date, 'entry_id' => $token['entry'], 'minutes' => 0, 'rate_minor' => $rate, 'multiplier' => $multiplier, 'amount_minor' => 0];
                }
                $groups[$groupKey]['minutes']++;
                if ($token['entry'] !== null && ($dailyOt || ($weeklyOt && $earnsOt))) {
                    $entryResults[$token['entry']]['is_overtime'] = true;
                }
            }
            if ($assignment->rate_type === CrewRateType::Daily) {
                $amount = $policy['prorate_partial_days'] ? $this->divide((string) ($assignment->rate_amount_minor * min(count($tokens), $dailyCall)), $dailyCall) : $assignment->rate_amount_minor;
                $first = $day['tokens'][0]['entry'];
                $groups['daily:'.$date] = ['code' => 'DAY_RATE', 'date' => $date, 'entry_id' => $first, 'minutes' => min(count($tokens), $dailyCall), 'rate_minor' => $assignment->rate_amount_minor, 'multiplier' => '1.00', 'amount_minor' => $amount];
            }
        }
        if ($assignment->rate_type === CrewRateType::Fixed && $days !== []) {
            $first = array_key_first($entryResults);
            $groups['fixed'] = ['code' => 'FIXED', 'date' => array_key_first($days), 'entry_id' => $first, 'minutes' => $totalMinutes, 'rate_minor' => $assignment->rate_amount_minor, 'multiplier' => '1.00', 'amount_minor' => $assignment->rate_amount_minor];
        }
        $totalPay = 0;
        $entryMultipliers = [];
        foreach ($groups as &$group) {
            if (! in_array($group['code'], ['DAY_RATE', 'FIXED'], true)) {
                $group['amount_minor'] = $this->divide(bcmul((string) ($group['minutes'] * $group['rate_minor']), $group['multiplier'], 6), 60);
            }
            $totalPay += $group['amount_minor'];
            if ($group['entry_id'] !== null) {
                $entryResults[$group['entry_id']]['amount_minor'] += $group['amount_minor'];
                $entryMultipliers[$group['entry_id']][$group['multiplier']] = true;
            }
        }
        unset($group);
        foreach ($entryMultipliers as $entryId => $multipliers) {
            $entryResults[$entryId]['multiplier'] = count($multipliers) === 1 ? (string) array_key_first($multipliers) : null;
        }
        $breakdown = array_values($groups);
        foreach ($snapshot as $key => $allowance) {
            if ($key === '_policy') {
                continue;
            }
            if (($allowance['currency'] ?? $assignment->currency_code) !== $assignment->currency_code) {
                throw ValidationException::withMessages(['allowances_snapshot' => 'Allowances must use the assignment currency.']);
            }
            $quantity = match ($allowance['per']) {
                'assignment' => $days === [] || in_array($allowance['code'], $policy['prior_assignment_allowances'] ?? [], true) ? 0 : 1,
                'day' => count(array_filter($days, static function (array $day) use ($policy): bool {
                    return count($day['tokens']) >= $policy['per_diem_min_minutes'];
                })),
                'night' => count($nightDates),
                'hour' => $totalMinutes,
                default => throw ValidationException::withMessages(['allowances_snapshot' => 'Unsupported allowance frequency.']),
            };
            $amount = $allowance['per'] === 'hour' ? $this->divide((string) ($quantity * (int) $allowance['amount_minor']), 60) : $quantity * (int) $allowance['amount_minor'];
            $totalPay += $amount;
            $breakdown[] = ['code' => $allowance['code'], 'quantity' => $quantity, 'amount_minor' => $amount];
        }

        return ['total_minutes' => $totalMinutes, 'total_pay_minor' => $totalPay, 'entries' => $entryResults, 'breakdown' => $breakdown, 'rounding_rule' => $policy['rounding_mode'].':'.$policy['rounding_increment_minutes']];
    }

    public function roundMinutes(int $minutes, string $mode, int $step): int
    {
        if ($step < 1) {
            throw ValidationException::withMessages(['rounding_increment_minutes' => 'The rounding step must be positive.']);
        }

        return match ($mode) {
            'none' => $minutes,
            'up' => intdiv($minutes + $step - 1, $step) * $step,
            'down' => intdiv($minutes, $step) * $step,
            'nearest' => intdiv($minutes * 2 + $step, $step * 2) * $step,
            default => throw ValidationException::withMessages(['rounding_mode' => 'Unsupported rounding mode.']),
        };
    }

    private function hoursToMinutes(string|int $hours): int
    {
        return $this->divide(bcmul((string) $hours, '60', 6), 1);
    }

    private function divide(string $numerator, int $denominator): int
    {
        return (int) bcadd(bcdiv($numerator, (string) $denominator, 6), '0.5', 0);
    }
}

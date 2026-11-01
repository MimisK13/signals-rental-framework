<?php

declare(strict_types=1);

namespace App\Domain\Crew\Queries;

use App\Domain\Crew\Data\Planning\CrewCostForecastData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewTimesheet;
use App\Domain\Crew\Services\CrewCompensationCalculator;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;

final class CrewCostForecastQuery
{
    public function forProject(Project $project): CrewCostForecastData
    {
        Gate::authorize('crew.reports.view');
        Gate::authorize('crew.rates.view');
        $assignments = CrewAssignment::query()->with(['request', 'shifts' => function ($query): void {
            $query->where('status', '!=', 'cancelled');
        }])->where('project_id', $project->id)->whereIn('status', ['proposed', 'invited', 'confirmed', 'cancelled'])->get();
        $currencies = [];
        $cancellationMinor = [];
        foreach ($assignments as $assignment) {
            $currency = $assignment->currency_code;
            if ($assignment->status->value === 'cancelled') {
                $entitlement = $assignment->cost_estimate_breakdown['cancellation'] ?? [];
                $amount = (int) ($entitlement['amount_minor'] ?? 0);
                if ($amount > 0) {
                    $cancellationMinor[$currency] = ($cancellationMinor[$currency] ?? 0) + $amount;
                }

                continue;
            }
            $currencies[$currency] ??= ['confirmed_minor' => 0, 'proposed_minor' => 0, 'approved_minor' => 0, 'unestimated_assignments' => 0];
            $estimate = $assignment->cost_estimate_minor;
            if ($estimate === null && $assignment->shifts->isNotEmpty()) {
                $entries = $assignment->shifts->map(static function ($shift): array {
                    return ['id' => $shift->id, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at,
                        'timezone' => $shift->timezone, 'break_minutes' => $shift->break_minutes,
                        'kind' => in_array($shift->type->value, ['travel', 'standby'], true) ? $shift->type->value : 'work'];
                });
                $estimate = app(CrewCompensationCalculator::class)->calculate($assignment, $entries)['total_pay_minor'];
            }
            $key = $assignment->status->value === 'confirmed' ? 'confirmed_minor' : 'proposed_minor';
            $currencies[$currency][$key] += $estimate ?? 0;
            if ($estimate === null) {
                $currencies[$currency]['unestimated_assignments']++;
            }
        }
        $approved = CrewTimesheet::query()->where('project_id', $project->id)->whereIn('status', ['approved', 'locked'])->selectRaw('currency_code, SUM(total_pay_minor) as approved_minor')->groupBy('currency_code')->get();
        foreach ($approved as $totals) {
            $currencies[$totals->currency_code] ??= ['confirmed_minor' => 0, 'proposed_minor' => 0, 'approved_minor' => 0, 'unestimated_assignments' => 0];
            $currencies[$totals->currency_code]['approved_minor'] = (int) $totals->getAttribute('approved_minor');
        }
        ksort($currencies);

        ksort($cancellationMinor);

        return new CrewCostForecastData($project->id, $currencies, $cancellationMinor);
    }
}

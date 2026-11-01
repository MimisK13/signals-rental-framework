<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewTimesheetStatus;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewTimesheet> */
class CrewTimesheetFactory extends Factory
{
    protected $model = CrewTimesheet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_assignment_id' => CrewAssignment::factory()->confirmed(),
            'crew_member_id' => function (array $attributes): int {
                return CrewAssignment::query()->findOrFail($attributes['crew_assignment_id'])->crew_member_id;
            },
            'project_id' => function (array $attributes): int {
                return CrewAssignment::query()->findOrFail($attributes['crew_assignment_id'])->project_id;
            },
            'store_id' => function (array $attributes): int {
                return CrewAssignment::query()->findOrFail($attributes['crew_assignment_id'])->store_id;
            },
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'status' => CrewTimesheetStatus::Draft,
            'total_minutes' => 0,
            'total_pay_minor' => 0,
            'currency_code' => 'GBP',
            'rounding_rule' => null,
            'overtime_breakdown' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'locked_at' => null,
            'exported_to_finance_at' => null,
            'finance_reference' => null,
            'version' => 1,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => CrewTimesheetStatus::Draft]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewTimesheetStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewTimesheetStatus::Approved,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewTimesheetStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => 'Rejected by test state',
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewTimesheetStatus::Locked,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'locked_at' => now(),
        ]);
    }

    public function withEntry(int $minutes = 480): static
    {
        return $this->afterCreating(function (CrewTimesheet $timesheet) use ($minutes): void {
            CrewTimeEntry::withoutEvents(function () use ($timesheet, $minutes): void {
                $timesheet->entries()->create([
                    'kind' => 'work',
                    'work_date' => $timesheet->period_start->toDateString(),
                    'started_at' => $timesheet->period_start->copy()->setTime(9, 0),
                    'ended_at' => $timesheet->period_start->copy()->setTime(9, 0)->addMinutes($minutes),
                    'break_minutes' => 0,
                    'minutes' => $minutes,
                    'is_overtime' => false,
                    'currency_code' => $timesheet->currency_code,
                ]);
            });
        });
    }

    public function withOvertime(): static
    {
        return $this->afterCreating(function (CrewTimesheet $timesheet): void {
            CrewTimeEntry::withoutEvents(function () use ($timesheet): void {
                $timesheet->entries()->create([
                    'kind' => 'work',
                    'work_date' => $timesheet->period_start->toDateString(),
                    'started_at' => $timesheet->period_start->copy()->setTime(9, 0),
                    'ended_at' => $timesheet->period_start->copy()->setTime(19, 0),
                    'break_minutes' => 0,
                    'minutes' => 600,
                    'is_overtime' => true,
                    'multiplier' => 1.50,
                    'currency_code' => $timesheet->currency_code,
                ]);
            });
        });
    }
}

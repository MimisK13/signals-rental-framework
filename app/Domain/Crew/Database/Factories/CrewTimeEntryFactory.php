<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewTimeEntryKind;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewTimeEntry> */
class CrewTimeEntryFactory extends Factory
{
    protected $model = CrewTimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workDate = now()->startOfWeek();
        $startedAt = $workDate->copy()->setTime(9, 0);
        $minutes = 480;

        return [
            'crew_timesheet_id' => CrewTimesheet::factory(),
            'crew_shift_id' => null,
            'kind' => CrewTimeEntryKind::Work,
            'work_date' => $workDate->toDateString(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes($minutes),
            'break_minutes' => 0,
            'minutes' => $minutes,
            'is_overtime' => false,
            'multiplier' => null,
            'applied_rate_minor' => null,
            'amount_minor' => null,
            'currency_code' => 'GBP',
            'source' => 'manual',
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function travel(): static
    {
        return $this->state(fn (): array => ['kind' => CrewTimeEntryKind::Travel]);
    }

    public function overtime(): static
    {
        return $this->state(fn (): array => [
            'is_overtime' => true,
            'multiplier' => 1.50,
        ]);
    }
}

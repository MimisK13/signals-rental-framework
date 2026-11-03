<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewShiftStatus;
use App\Domain\Crew\Enums\CrewShiftType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewShift;
use App\Domain\Projects\Models\Project;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<CrewShift> */
class CrewShiftFactory extends Factory
{
    protected $model = CrewShift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now()->addWeek()->setTime(9, 0);

        return [
            'store_id' => Store::factory(),
            'project_id' => Project::factory(),
            'crew_assignment_id' => null,
            'crew_member_id' => null,
            'venue_member_id' => null,
            'status' => CrewShiftStatus::Planned,
            'type' => CrewShiftType::Show,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(8),
            'timezone' => 'UTC',
            'break_minutes' => 0,
            'is_overnight' => false,
            'location_name' => null,
            'notes' => null,
            'sequence' => 0,
            'created_by' => null,
        ];
    }

    public function forAssignment(CrewAssignment $assignment): static
    {
        return $this->state(fn (): array => [
            'store_id' => $assignment->store_id,
            'project_id' => $assignment->project_id,
            'crew_assignment_id' => $assignment->id,
            'crew_member_id' => $assignment->crew_member_id,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (): array => ['status' => CrewShiftStatus::Planned]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => ['status' => CrewShiftStatus::Confirmed]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => ['status' => CrewShiftStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => CrewShiftStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => CrewShiftStatus::Cancelled]);
    }

    public function setup(): static
    {
        return $this->state(fn (): array => ['type' => CrewShiftType::Setup]);
    }

    public function show(): static
    {
        return $this->state(fn (): array => ['type' => CrewShiftType::Show]);
    }

    public function strike(): static
    {
        return $this->state(fn (): array => ['type' => CrewShiftType::Strike]);
    }

    public function travel(): static
    {
        return $this->state(fn (): array => ['type' => CrewShiftType::Travel]);
    }

    public function overnight(): static
    {
        return $this->state(function (): array {
            $startsAt = Carbon::now()->addWeek()->setTime(22, 0);

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(8),
                'is_overnight' => true,
            ];
        });
    }

    public function acrossSpringForward(): static
    {
        return $this->state(function (): array {
            $startsAt = Carbon::parse('2026-03-29 00:00:00', 'Europe/London')->utc();

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(8),
                'timezone' => 'Europe/London',
                'is_overnight' => true,
            ];
        });
    }

    public function acrossFallBack(): static
    {
        return $this->state(function (): array {
            $startsAt = Carbon::parse('2026-10-25 00:00:00', 'Europe/London')->utc();

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(8),
                'timezone' => 'Europe/London',
                'is_overnight' => true,
            ];
        });
    }
}

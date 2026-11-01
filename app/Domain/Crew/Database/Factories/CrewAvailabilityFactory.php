<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewAvailabilityStatus;
use App\Domain\Crew\Enums\CrewAvailabilityType;
use App\Domain\Crew\Models\CrewAvailability;
use App\Domain\Crew\Models\CrewMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<CrewAvailability> */
class CrewAvailabilityFactory extends Factory
{
    protected $model = CrewAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::now()->addDays(3)->setTime(9, 0);

        return [
            'crew_member_id' => CrewMember::factory(),
            'type' => CrewAvailabilityType::Unavailable,
            'status' => CrewAvailabilityStatus::Requested,
            'all_day' => false,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(8),
            'timezone' => 'UTC',
            'reason' => null,
            'notes' => null,
            'requested_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => ['status' => CrewAvailabilityStatus::Requested]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAvailabilityStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => CrewAvailabilityStatus::Rejected]);
    }

    public function leave(): static
    {
        return $this->state(fn (): array => ['type' => CrewAvailabilityType::Leave]);
    }

    public function sick(): static
    {
        return $this->state(fn (): array => ['type' => CrewAvailabilityType::Sick]);
    }

    public function allDay(): static
    {
        return $this->state(fn (array $attributes): array => [
            'all_day' => true,
            'starts_at' => Carbon::parse($attributes['starts_at'])->startOfDay(),
            'ends_at' => Carbon::parse($attributes['starts_at'])->endOfDay(),
        ]);
    }

    public function covering(Carbon $start, Carbon $end): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }
}

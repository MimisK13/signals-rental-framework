<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewEngagementType;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRole;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewRateCard> */
class CrewRateCardFactory extends Factory
{
    protected $model = CrewRateCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => null,
            'crew_member_id' => null,
            'crew_role_id' => null,
            'engagement_type' => null,
            'currency_code' => 'GBP',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'hourly_rate_minor' => 4500,
            'daily_rate_minor' => null,
            'fixed_rate_minor' => null,
            'overtime_multiplier' => 1.50,
            'weekend_multiplier' => null,
            'night_multiplier' => null,
            'holiday_multiplier' => null,
            'min_call_hours' => null,
            'daily_call_hours' => null,
            'travel_rate_minor' => null,
            'per_diem_minor' => null,
            'notes' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function hourly(int $minor = 4500): static
    {
        return $this->state(fn (): array => [
            'hourly_rate_minor' => $minor,
            'daily_rate_minor' => null,
            'fixed_rate_minor' => null,
        ]);
    }

    public function daily(int $minor = 35000): static
    {
        return $this->state(fn (): array => [
            'hourly_rate_minor' => null,
            'daily_rate_minor' => $minor,
            'fixed_rate_minor' => null,
        ]);
    }

    public function fixed(int $minor = 50000): static
    {
        return $this->state(fn (): array => [
            'hourly_rate_minor' => null,
            'daily_rate_minor' => null,
            'fixed_rate_minor' => $minor,
        ]);
    }

    public function forMember(CrewMember $member): static
    {
        return $this->state(fn (): array => ['crew_member_id' => $member->id]);
    }

    public function forRole(CrewRole $role): static
    {
        return $this->state(fn (): array => ['crew_role_id' => $role->id]);
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (): array => ['store_id' => $store->id]);
    }

    public function forEngagement(CrewEngagementType $type): static
    {
        return $this->state(fn (): array => ['engagement_type' => $type]);
    }

    public function effectiveFrom(\DateTimeInterface $date): static
    {
        return $this->state(fn (): array => ['effective_from' => $date->format('Y-m-d')]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => now()->subDay()->toDateString(),
        ]);
    }
}

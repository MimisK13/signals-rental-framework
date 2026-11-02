<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewRequestPriority;
use App\Domain\Crew\Enums\CrewRequestStatus;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRole;
use App\Domain\Projects\Models\Project;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewRequest> */
class CrewRequestFactory extends Factory
{
    protected $model = CrewRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'project_id' => Project::factory(),
            'opportunity_id' => null,
            'venue_member_id' => null,
            'reference' => null,
            'title' => fake()->sentence(3),
            'status' => CrewRequestStatus::Draft,
            'priority' => CrewRequestPriority::Normal,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeeks(2),
            'timezone' => 'UTC',
            'location_name' => fake()->optional()->city(),
            'notes' => null,
            'requested_by' => null,
            'published_at' => null,
            'approved_at' => null,
            'approved_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => CrewRequestStatus::Draft]);
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewRequestStatus::Open,
            'published_at' => now(),
        ]);
    }

    public function partiallyFilled(): static
    {
        return $this->state(fn (): array => ['status' => CrewRequestStatus::PartiallyFilled]);
    }

    public function filled(): static
    {
        return $this->state(fn (): array => ['status' => CrewRequestStatus::Filled]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => ['project_id' => $project->id]);
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (): array => ['store_id' => $store->id]);
    }

    public function withLine(?CrewRole $role = null, int $quantity = 1): static
    {
        return $this->afterCreating(function (CrewRequest $request) use ($role, $quantity): void {
            $request->lines()->create([
                'crew_role_id' => $role === null ? CrewRole::factory()->create()->id : $role->id,
                'quantity' => $quantity,
                'filled_quantity' => 0,
            ]);
        });
    }
}

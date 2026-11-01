<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewAssignmentStatus;
use App\Domain\Crew\Enums\CrewRateType;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewAssignment> */
class CrewAssignmentFactory extends Factory
{
    protected $model = CrewAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crew_request_id' => CrewRequest::factory(),
            'crew_request_line_id' => function (array $attributes): int {
                return CrewRequestLine::factory()
                    ->create(['crew_request_id' => $attributes['crew_request_id']])
                    ->id;
            },
            'crew_member_id' => CrewMember::factory(),
            'project_id' => function (array $attributes): int {
                return CrewRequest::query()->findOrFail($attributes['crew_request_id'])->project_id;
            },
            'store_id' => function (array $attributes): int {
                return CrewRequest::query()->findOrFail($attributes['crew_request_id'])->store_id;
            },
            'status' => CrewAssignmentStatus::Proposed,
            'active_assignment_key' => function (array $attributes): string {
                return CrewAssignment::activeKeyFor(
                    $attributes['crew_request_line_id'],
                    $attributes['crew_member_id'],
                );
            },
            'rate_card_id' => null,
            'rate_type' => CrewRateType::Hourly,
            'rate_amount_minor' => 4500,
            'currency_code' => 'GBP',
            'exchange_rate_to_base' => 1,
            'overtime_multiplier' => 1.50,
            'min_call_hours' => null,
            'daily_call_hours' => null,
            'allowances_snapshot' => null,
            'cost_estimate_minor' => null,
            'cost_estimate_breakdown' => null,
            'agreed_at' => null,
            'agreed_by' => null,
            'invited_at' => null,
            'responded_at' => null,
            'confirmed_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'substitute_for_assignment_id' => null,
            'requires_realignment' => false,
            'realignment_flagged_at' => null,
            'realignment_reason' => null,
            'created_by' => null,
        ];
    }

    public function proposed(): static
    {
        return $this->state(fn (): array => ['status' => CrewAssignmentStatus::Proposed]);
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAssignmentStatus::Invited,
            'invited_at' => now(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAssignmentStatus::Confirmed,
            'invited_at' => now()->subDay(),
            'responded_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAssignmentStatus::Rejected,
            'active_assignment_key' => null,
            'responded_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAssignmentStatus::Cancelled,
            'active_assignment_key' => null,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by test state',
        ]);
    }

    public function substituted(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewAssignmentStatus::Substituted,
            'active_assignment_key' => null,
        ]);
    }

    public function forLine(CrewRequestLine $line): static
    {
        $request = $line->request;

        return $this->state(fn (): array => [
            'crew_request_id' => $line->crew_request_id,
            'crew_request_line_id' => $line->id,
            'project_id' => $request->project_id,
            'store_id' => $request->store_id,
        ]);
    }

    public function forMember(CrewMember $member): static
    {
        return $this->state(fn (): array => ['crew_member_id' => $member->id]);
    }
}

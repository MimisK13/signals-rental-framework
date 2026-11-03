<?php

declare(strict_types=1);

namespace App\Domain\Crew\Database\Factories;

use App\Domain\Crew\Enums\CrewExpenseCategory;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Projects\Models\Project;
use App\Domain\Stores\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CrewExpense> */
class CrewExpenseFactory extends Factory
{
    protected $model = CrewExpense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(500, 50000);

        return [
            'store_id' => Store::factory(),
            'project_id' => Project::factory(),
            'crew_member_id' => CrewMember::factory(),
            'crew_assignment_id' => null,
            'category' => CrewExpenseCategory::Travel,
            'status' => CrewExpenseStatus::Draft,
            'incurred_on' => now()->toDateString(),
            'description' => fake()->sentence(4),
            'amount_minor' => $amount,
            'currency_code' => 'GBP',
            'exchange_rate' => 1,
            'amount_base_minor' => $amount,
            'billable_to_client' => false,
            'markup_percent' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_reason' => null,
            'reimbursed_at' => null,
            'finance_reference' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewExpenseStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewExpenseStatus::Approved,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    public function reimbursed(): static
    {
        return $this->state(fn (): array => [
            'status' => CrewExpenseStatus::Reimbursed,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'reimbursed_at' => now(),
        ]);
    }
}

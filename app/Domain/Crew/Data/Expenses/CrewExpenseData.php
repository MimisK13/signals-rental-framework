<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Expenses;

use App\Domain\Crew\Models\CrewExpense;
use Spatie\LaravelData\Data;

final class CrewExpenseData extends Data
{
    public function __construct(
        public int $id,
        public int $crew_member_id,
        public int $store_id,
        public int $project_id,
        public ?int $crew_assignment_id,
        public string $category,
        public string $status,
        public string $incurred_on,
        public string $description,
        public int $amount_minor,
        public string $currency_code,
        public int $amount_base_minor,
        public ?string $finance_reference,
    ) {}

    public static function fromModel(CrewExpense $expense): self
    {
        return new self($expense->id, $expense->crew_member_id, $expense->store_id, $expense->project_id, $expense->crew_assignment_id, $expense->category->value, $expense->status->value, $expense->incurred_on->toDateString(), $expense->description, $expense->amount_minor, $expense->currency_code, $expense->amount_base_minor, $expense->finance_reference);
    }
}

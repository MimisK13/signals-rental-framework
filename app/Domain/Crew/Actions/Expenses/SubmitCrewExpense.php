<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SubmitCrewExpense
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewExpense $expense): CrewExpenseData
    {
        Gate::authorize('submit', $expense);

        return DB::transaction(function () use ($expense): CrewExpenseData {
            $expense = $this->editor->lock($expense, 'submit');
            if ($expense->status !== CrewExpenseStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'Correct the rejected expense before submitting it again.']);
            }
            $expense->fill(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => auth()->id()]);
            $expense->save();
            CrewAudit::record('crew.expense.submitted', $expense);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

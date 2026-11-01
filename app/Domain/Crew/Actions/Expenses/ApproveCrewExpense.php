<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ApproveCrewExpense
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewExpense $expense): CrewExpenseData
    {
        Gate::authorize('approve', $expense);

        return DB::transaction(function () use ($expense): CrewExpenseData {
            $expense = $this->editor->lock($expense, 'approve');

            $expense->fill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
            $expense->save();
            CrewAudit::record('crew.expense.approved', $expense);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

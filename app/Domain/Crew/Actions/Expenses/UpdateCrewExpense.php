<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Data\Expenses\UpdateCrewExpenseData;
use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UpdateCrewExpense
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewExpense $expense, UpdateCrewExpenseData $data): CrewExpenseData
    {
        Gate::authorize('update', $expense);

        return DB::transaction(function () use ($expense, $data): CrewExpenseData {
            $expense = $this->editor->lock($expense, 'update');
            $this->editor->fill($expense, $data->toArray());
            $expense->status = CrewExpenseStatus::Draft;
            $expense->save();
            CrewAudit::record('crew.expense.updated', $expense);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

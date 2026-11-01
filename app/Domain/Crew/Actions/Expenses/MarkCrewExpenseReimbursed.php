<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Data\Expenses\MarkReimbursedData;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class MarkCrewExpenseReimbursed
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewExpense $expense, MarkReimbursedData $data): CrewExpenseData
    {
        Gate::authorize('reimburse', $expense);
        Validator::make($data->toArray(), ['finance_reference' => ['required', 'string', 'max:80']])->validate();

        return DB::transaction(function () use ($expense, $data): CrewExpenseData {
            $expense = $this->editor->lock($expense, 'reimburse');
            $expense->fill(['status' => 'reimbursed', 'finance_reference' => $data->finance_reference, 'reimbursed_at' => now()])->save();
            CrewAudit::record('crew.expense.reimbursed', $expense, ['finance_reference' => $data->finance_reference]);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

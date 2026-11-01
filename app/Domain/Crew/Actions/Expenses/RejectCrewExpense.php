<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Data\Expenses\RejectCrewExpenseData;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class RejectCrewExpense
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewExpense $expense, RejectCrewExpenseData $data): CrewExpenseData
    {
        Gate::authorize('reject', $expense);
        Validator::make($data->toArray(), ['reason' => ['required', 'string', 'max:10000']])->validate();

        return DB::transaction(function () use ($expense, $data): CrewExpenseData {
            $expense = $this->editor->lock($expense, 'reject');
            $expense->fill(['status' => 'rejected', 'rejected_reason' => $data->reason])->save();
            CrewAudit::record('crew.expense.rejected', $expense, ['reason' => $data->reason]);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

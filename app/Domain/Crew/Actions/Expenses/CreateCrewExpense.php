<?php

declare(strict_types=1);

namespace App\Domain\Crew\Actions\Expenses;

use App\Domain\Crew\Data\Expenses\CreateCrewExpenseData;
use App\Domain\Crew\Data\Expenses\CrewExpenseData;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Services\CrewAudit;
use App\Domain\Crew\Services\CrewExpenseEditor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateCrewExpense
{
    public function __construct(private CrewExpenseEditor $editor) {}

    public function __invoke(CrewMember $member, CreateCrewExpenseData $data): CrewExpenseData
    {
        Gate::authorize('create', CrewExpense::class);

        return DB::transaction(function () use ($member, $data): CrewExpenseData {
            $member = CrewMember::query()->lockForUpdate()->findOrFail($member->id);
            $expense = new CrewExpense(['crew_member_id' => $member->id, 'status' => 'draft']);
            $this->editor->fill($expense, $data->toArray());
            $expense->save();
            CrewAudit::record('crew.expense.created', $expense);

            return CrewExpenseData::fromModel($expense);
        });
    }
}

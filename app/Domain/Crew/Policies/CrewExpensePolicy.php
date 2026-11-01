<?php

declare(strict_types=1);

namespace App\Domain\Crew\Policies;

use App\Domain\Crew\Enums\CrewExpenseStatus;
use App\Domain\Crew\Models\CrewExpense;
use App\Domain\Crew\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;

class CrewExpensePolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('crew.expenses.view');
    }

    public function view(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.view')
            && $this->canAccessStore($user, $expense->store_id);
    }

    public function create(User $user): bool
    {
        return $user->can('crew.expenses.create');
    }

    public function update(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.create')
            && $this->canAccessStore($user, $expense->store_id)
            && in_array($expense->status, [CrewExpenseStatus::Draft, CrewExpenseStatus::Rejected], true);
    }

    public function submit(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.create')
            && $this->canAccessStore($user, $expense->store_id)
            && in_array($expense->status, [CrewExpenseStatus::Draft, CrewExpenseStatus::Rejected], true);
    }

    public function approve(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.approve')
            && $this->canAccessStore($user, $expense->store_id)
            && $expense->status === CrewExpenseStatus::Submitted;
    }

    public function reject(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.approve')
            && $this->canAccessStore($user, $expense->store_id)
            && $expense->status === CrewExpenseStatus::Submitted;
    }

    public function reimburse(User $user, CrewExpense $expense): bool
    {
        return $user->can('crew.expenses.approve')
            && $this->canAccessStore($user, $expense->store_id)
            && $expense->status === CrewExpenseStatus::Approved;
    }
}

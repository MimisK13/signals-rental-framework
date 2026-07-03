<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;
use Illuminate\Database\Eloquent\Model;

class InvoicePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'invoices.view';
    }

    protected function managePermission(): string
    {
        return 'invoices.create';
    }

    protected function createPermission(): string
    {
        return 'invoices.create';
    }

    protected function editPermission(): string
    {
        return 'invoices.edit';
    }

    protected function deletePermission(): string
    {
        return 'invoices.delete';
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can($this->editPermission())) {
            return false;
        }

        if ($model instanceof Invoice) {
            return ! $model->is_locked && $model->status === 'draft';
        }

        return true;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.issue')) {
            return false;
        }

        return ! $invoice->is_locked && $invoice->status === 'draft';
    }

    public function void(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.void')) {
            return false;
        }

        return $invoice->status !== 'void';
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        if (! $user->can('payments.record')) {
            return false;
        }

        return ! in_array($invoice->status, ['draft', 'void', 'credited'], true);
    }

    public function issueCredit(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.credit')) {
            return false;
        }

        return ! in_array($invoice->status, ['draft', 'void', 'credited'], true);
    }
}

<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class CreditNotePolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can('invoices.view')) {
            return false;
        }

        if ($model instanceof CreditNote) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }
}

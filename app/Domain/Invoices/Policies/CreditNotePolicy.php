<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Policies;

use App\Domain\Invoices\Models\CreditNote;
use App\Domain\Stores\Policies\Concerns\ChecksStoreAccess;
use App\Models\User;
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

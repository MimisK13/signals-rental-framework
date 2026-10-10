<?php

namespace App\Policies;

use App\Models\Repair;
use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;

class RepairPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, Repair $repair): bool
    {
        return $user->can('stock.view');
    }

    public function create(User $user, ?Repair $repair = null): bool
    {
        return $user->can('stock.adjust')
            && ($repair === null || $this->canAccessStore($user, $repair));
    }

    public function update(User $user, Repair $repair): bool
    {
        return $user->can('stock.adjust') && $this->canAccessStore($user, $repair);
    }

    public function delete(User $user, Repair $repair): bool
    {
        return $user->can('stock.adjust') && $this->canAccessStore($user, $repair);
    }
}

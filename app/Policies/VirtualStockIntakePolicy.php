<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VirtualStockIntake;
use App\Policies\Traits\ChecksStoreAccess;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use Illuminate\Database\Eloquent\Model;

class VirtualStockIntakePolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('virtual_stock.view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can('virtual_stock.view')) {
            return false;
        }

        if ($model instanceof VirtualStockIntake) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('costs.view') && $user->can('virtual_stock.create');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.edit')) {
            return false;
        }

        if ($model instanceof VirtualStockIntake) {
            return $model->status === 'draft' && $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.delete')) {
            return false;
        }

        if ($model instanceof VirtualStockIntake) {
            return $model->status === 'draft' && $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function confirm(User $user, VirtualStockIntake $intake): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.confirm')) {
            return false;
        }

        return $this->canAccessStore($user, $intake)
            && app(VirtualStockStatusRegistry::class)->canTransitionTo($intake->status, 'confirmed');
    }

    public function receive(User $user, VirtualStockIntake $intake): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.receive')) {
            return false;
        }

        return $this->canAccessStore($user, $intake)
            && app(VirtualStockStatusRegistry::class)->canTransitionTo($intake->status, 'received');
    }

    public function return(User $user, VirtualStockIntake $intake): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.return')) {
            return false;
        }

        return $this->canAccessStore($user, $intake)
            && app(VirtualStockStatusRegistry::class)->canTransitionTo($intake->status, 'returned');
    }

    public function cancel(User $user, VirtualStockIntake $intake): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.cancel')) {
            return false;
        }

        return $this->canAccessStore($user, $intake)
            && app(VirtualStockStatusRegistry::class)->canTransitionTo($intake->status, 'cancelled');
    }

    public function manageCostOverrides(User $user, VirtualStockIntake $intake): bool
    {
        if (! $user->can('costs.view') || ! $user->can('virtual_stock.edit')) {
            return false;
        }

        if (! in_array($intake->status, ['confirmed', 'received'], true)) {
            return false;
        }

        return $this->canAccessStore($user, $intake);
    }
}

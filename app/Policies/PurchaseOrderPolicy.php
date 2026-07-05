<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can('purchase_orders.view')) {
            return false;
        }

        if ($model instanceof PurchaseOrder) {
            return $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('costs.view') && $user->can('purchase_orders.create');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        if (! $user->can('costs.view') || ! $user->can('purchase_orders.edit')) {
            return false;
        }

        if ($model instanceof PurchaseOrder) {
            return $model->status === 'draft' && $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        if (! $user->can('costs.view') || ! $user->can('purchase_orders.delete')) {
            return false;
        }

        if ($model instanceof PurchaseOrder) {
            return $model->status === 'draft' && $this->canAccessStore($user, $model);
        }

        return true;
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('costs.view') || ! $user->can('purchase_orders.send')) {
            return false;
        }

        return $purchaseOrder->status === 'draft' && $this->canAccessStore($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('costs.view') || ! $user->can('purchase_orders.cancel')) {
            return false;
        }

        return $this->canAccessStore($user, $purchaseOrder);
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->can('costs.view') || ! $user->can('purchase_orders.receive')) {
            return false;
        }

        if (! in_array($purchaseOrder->status, ['sent', 'partially_received'], true)) {
            return false;
        }

        return $this->canAccessStore($user, $purchaseOrder);
    }
}

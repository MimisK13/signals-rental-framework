<?php

namespace App\Policies;

use App\Models\ProductSupplierCost;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductSupplierCostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('costs.view') && $user->can('products.view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('costs.view') && $user->can('products.supplier_costs');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->create($user);
    }

    public function setPreferred(User $user, ProductSupplierCost $cost): bool
    {
        return $this->create($user);
    }
}

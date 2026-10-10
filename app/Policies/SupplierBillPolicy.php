<?php

namespace App\Policies;

use App\Enums\SupplierBillStatus;
use App\Models\SupplierBill;
use App\Models\User;
use App\Policies\Traits\ChecksStoreAccess;
use Illuminate\Database\Eloquent\Model;

class SupplierBillPolicy
{
    use ChecksStoreAccess;

    public function viewAny(User $user): bool
    {
        return $user->can('supplier_bills.view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        if (! $user->can('supplier_bills.view')) {
            return false;
        }

        return ! $model instanceof SupplierBill || $this->canAccessStore($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('costs.view') && $user->can('supplier_bills.create');
    }

    public function update(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('costs.view')
            && $user->can('supplier_bills.edit')
            && $supplierBill->statusEnum() === SupplierBillStatus::Draft
            && $this->canAccessStore($user, $supplierBill);
    }

    public function delete(User $user, SupplierBill $supplierBill): bool
    {
        return $this->update($user, $supplierBill) && $user->can('supplier_bills.delete');
    }

    public function issue(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('costs.view')
            && $user->can('supplier_bills.issue')
            && $supplierBill->statusEnum() === SupplierBillStatus::Draft
            && $this->canAccessStore($user, $supplierBill);
    }

    public function void(User $user, SupplierBill $supplierBill): bool
    {
        return $user->can('costs.view')
            && $user->can('supplier_bills.void')
            && $supplierBill->statusEnum() === SupplierBillStatus::Issued
            && $this->canAccessStore($user, $supplierBill);
    }
}

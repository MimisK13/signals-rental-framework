<?php

namespace App\Actions\SupplierBills;

use App\Enums\SupplierBillStatus;
use App\Events\AuditableEvent;
use App\Models\SupplierBill;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VoidSupplierBill
{
    public function __invoke(SupplierBill $bill): SupplierBill
    {
        Gate::authorize('void', $bill);

        if ($bill->statusEnum() !== SupplierBillStatus::Issued) {
            throw ValidationException::withMessages(['supplier_bill' => ['Only issued supplier bills can be voided.']]);
        }

        $bill->update(['status' => SupplierBillStatus::Void]);
        event(new AuditableEvent($bill->refresh(), 'supplier_bill.voided'));

        return $bill;
    }
}

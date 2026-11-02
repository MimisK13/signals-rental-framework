<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Actions;

use App\Domain\SupplierBills\Enums\SupplierBillStatus;
use App\Domain\SupplierBills\Models\SupplierBill;
use App\Events\AuditableEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IssueSupplierBill
{
    public function __invoke(SupplierBill $bill): SupplierBill
    {
        Gate::authorize('issue', $bill);

        if ($bill->statusEnum() !== SupplierBillStatus::Draft) {
            throw ValidationException::withMessages(['supplier_bill' => ['Only draft supplier bills can be issued.']]);
        }

        $bill->update([
            'status' => SupplierBillStatus::Issued,
            'issued_at' => CarbonImmutable::now('UTC'),
        ]);
        event(new AuditableEvent($bill->refresh(), 'supplier_bill.issued'));

        return $bill;
    }
}

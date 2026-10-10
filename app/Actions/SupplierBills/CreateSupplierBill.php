<?php

namespace App\Actions\SupplierBills;

use App\Enums\SupplierBillStatus;
use App\Events\AuditableEvent;
use App\Models\SupplierBill;
use App\Services\SupplierBills\SupplierBillTotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateSupplierBill
{
    public function __construct(private readonly SupplierBillTotalsCalculator $totals) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function __invoke(array $attributes, array $lines): SupplierBill
    {
        Gate::authorize('create', SupplierBill::class);

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => ['At least one bill line is required.']]);
        }

        $currency = strtoupper((string) ($attributes['currency_code'] ?? config('money.base_currency', 'EUR')));
        $totals = $this->totals->calculate($currency, $lines);

        $bill = DB::transaction(function () use ($attributes, $lines, $currency, $totals): SupplierBill {
            $bill = SupplierBill::query()->create([
                ...$attributes,
                'currency_code' => $currency,
                'status' => SupplierBillStatus::Draft,
                'issued_at' => null,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ]);

            foreach ($lines as $index => $line) {
                $bill->items()->create([
                    ...$line,
                    'total' => $totals['lines'][$index]['total'],
                    'tax' => $totals['lines'][$index]['tax'],
                ]);
            }

            return $bill;
        });

        event(new AuditableEvent($bill, 'supplier_bill.created'));

        return $bill->load('items');
    }
}

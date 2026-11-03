<?php

declare(strict_types=1);

namespace App\Domain\SupplierBills\Actions;

use App\Domain\AuditLog\Events\AuditableEvent;
use App\Domain\SupplierBills\Enums\SupplierBillStatus;
use App\Domain\SupplierBills\Models\SupplierBill;
use App\Domain\SupplierBills\Services\SupplierBillTotalsCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateDraftSupplierBill
{
    public function __construct(private readonly SupplierBillTotalsCalculator $totals) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function __invoke(SupplierBill $bill, array $attributes, array $lines): SupplierBill
    {
        Gate::authorize('update', $bill);

        if ($bill->statusEnum() !== SupplierBillStatus::Draft) {
            throw ValidationException::withMessages(['supplier_bill' => ['Only draft supplier bills can be edited.']]);
        }

        $currency = strtoupper((string) ($attributes['currency_code'] ?? $bill->currency_code));
        $totals = $this->totals->calculate($currency, $lines);

        DB::transaction(function () use ($bill, $attributes, $lines, $currency, $totals): void {
            $bill->update([
                ...$attributes,
                'currency_code' => $currency,
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'total' => $totals['total'],
            ]);
            $bill->items()->delete();

            foreach ($lines as $index => $line) {
                $bill->items()->create([
                    ...$line,
                    'total' => $totals['lines'][$index]['total'],
                    'tax' => $totals['lines'][$index]['tax'],
                ]);
            }
        });

        event(new AuditableEvent($bill->refresh(), 'supplier_bill.updated'));

        return $bill->load('items');
    }
}

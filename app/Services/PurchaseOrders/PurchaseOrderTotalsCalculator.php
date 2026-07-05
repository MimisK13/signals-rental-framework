<?php

namespace App\Services\PurchaseOrders;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Computes purchase order line and header totals at fire-time using brick/money.
 */
class PurchaseOrderTotalsCalculator
{
    /**
     * @param  list<array{quantity: string, unit_cost: int, tax?: int}>  $lines
     * @return array{
     *     lines: list<array{quantity: string, unit_cost: int, tax: int, total: int, sort_order: int}>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int
     * }
     */
    public function calculate(string $currencyCode, array $lines, int $roundingAdjustment = 0): array
    {
        $currency = strtoupper($currencyCode);
        $subtotal = Money::zero($currency);
        $taxTotal = Money::zero($currency);
        $computedLines = [];

        foreach ($lines as $index => $line) {
            $quantity = bcadd((string) $line['quantity'], '0', 2);
            $unitCost = (int) $line['unit_cost'];
            $lineTax = (int) ($line['tax'] ?? 0);

            $lineNet = Money::ofMinor($unitCost, $currency)
                ->multipliedBy($quantity, RoundingMode::HALF_UP);

            $lineTaxMoney = Money::ofMinor($lineTax, $currency);
            $lineTotal = $lineNet->plus($lineTaxMoney);

            $subtotal = $subtotal->plus($lineNet);
            $taxTotal = $taxTotal->plus($lineTaxMoney);

            $computedLines[] = [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'tax' => $lineTax,
                'total' => $lineTotal->getMinorAmount()->toInt(),
                'sort_order' => $index,
            ];
        }

        $adjustment = Money::ofMinor($roundingAdjustment, $currency);
        $grandTotal = $subtotal->plus($taxTotal)->plus($adjustment);

        return [
            'lines' => $computedLines,
            'subtotal' => $subtotal->getMinorAmount()->toInt(),
            'tax_total' => $taxTotal->getMinorAmount()->toInt(),
            'total' => $grandTotal->getMinorAmount()->toInt(),
        ];
    }
}

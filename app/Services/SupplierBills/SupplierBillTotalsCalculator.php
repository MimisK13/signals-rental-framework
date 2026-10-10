<?php

namespace App\Services\SupplierBills;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

class SupplierBillTotalsCalculator
{
    /**
     * @param  list<array{quantity: string, unit_cost: int, tax?: int}>  $lines
     * @return array{subtotal: int, tax_total: int, total: int, lines: list<array{total: int, tax: int, sort_order: int}>}
     */
    public function calculate(string $currencyCode, array $lines): array
    {
        $currency = strtoupper($currencyCode);
        $subtotal = Money::zero($currency);
        $taxTotal = Money::zero($currency);
        $calculated = [];

        foreach ($lines as $index => $line) {
            $lineNet = Money::ofMinor((int) $line['unit_cost'], $currency)
                ->multipliedBy((string) $line['quantity'], RoundingMode::HALF_UP);
            $lineTax = Money::ofMinor((int) ($line['tax'] ?? 0), $currency);

            $subtotal = $subtotal->plus($lineNet);
            $taxTotal = $taxTotal->plus($lineTax);
            $calculated[] = [
                'total' => $lineNet->plus($lineTax)->getMinorAmount()->toInt(),
                'tax' => $lineTax->getMinorAmount()->toInt(),
                'sort_order' => $index,
            ];
        }

        return [
            'subtotal' => $subtotal->getMinorAmount()->toInt(),
            'tax_total' => $taxTotal->getMinorAmount()->toInt(),
            'total' => $subtotal->plus($taxTotal)->getMinorAmount()->toInt(),
            'lines' => $calculated,
        ];
    }
}

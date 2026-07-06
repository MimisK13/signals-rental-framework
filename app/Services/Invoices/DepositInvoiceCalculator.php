<?php

namespace App\Services\Invoices;

use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Enums\PartialInvoiceTaxPolicy;
use App\Enums\TaxResultType;
use LogicException;

/**
 * Derives deposit invoice lines and tax rows from a full opportunity snapshot.
 *
 * Reads {@see settings('finance.partial_invoice_tax')} at call-time (passed in
 * by the action). INVARIANT: Σ(line tax) ≡ Σ(group tax) to the penny.
 */
class DepositInvoiceCalculator
{
    /**
     * @param  list<FrozenInvoiceTaxRowData>  $fullTaxRows
     * @return array{
     *     lines: list<FrozenInvoiceLineData>,
     *     tax_rows: list<FrozenInvoiceTaxRowData>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int,
     * }
     */
    public function calculate(
        int $depositNet,
        int $fullSubtotal,
        array $fullTaxRows,
        PartialInvoiceTaxPolicy $taxPolicy,
        string $depositDescription = 'Deposit',
    ): array {
        if ($depositNet <= 0) {
            throw new LogicException('Deposit net amount must be greater than zero.');
        }

        if ($fullSubtotal <= 0) {
            throw new LogicException('Cannot calculate deposit tax against zero opportunity subtotal.');
        }

        $taxRows = match ($taxPolicy) {
            PartialInvoiceTaxPolicy::Proportional => $this->scaleTaxRowsProportionally(
                $fullTaxRows,
                $depositNet,
                $fullSubtotal,
            ),
            PartialInvoiceTaxPolicy::Full => $this->mirrorFullTaxRows($fullTaxRows),
        };

        $taxTotal = array_sum(array_map(
            fn (FrozenInvoiceTaxRowData $row): int => $row->tax_amount,
            $taxRows,
        ));

        $line = new FrozenInvoiceLineData(
            invoice_item_id: 0,
            line_key: 'deposit',
            source_type: 'deposit',
            description: $depositDescription,
            quantity: '1',
            unit_price: $depositNet,
            discount: 0,
            net_total: $depositNet,
            tax: $taxTotal,
            product_tax_class_id: $fullTaxRows[0]->product_tax_class_key ?? 0,
            tax_treatment: $this->resolveLineTaxTreatment($taxRows),
            sort_order: 0,
        );

        $this->assertPennyReconciliation($line->tax, $taxRows);

        return [
            'lines' => [$line],
            'tax_rows' => $taxRows,
            'subtotal' => $depositNet,
            'tax_total' => $taxTotal,
            'total' => $depositNet + $taxTotal,
        ];
    }

    /**
     * Compute deposit net from a percentage of the opportunity subtotal.
     */
    public function netFromPercentage(int $fullSubtotal, string $percentage): int
    {
        $exact = bcdiv(bcmul((string) $fullSubtotal, $percentage, 10), '100', 10);

        return (int) bcadd($exact, '0.5', 0);
    }

    /**
     * Compute deposit net from a fixed gross amount using the opportunity's net:gross ratio.
     */
    public function netFromFixedGross(int $fullSubtotal, int $fullGross, int $fixedGross): int
    {
        if ($fullGross <= 0) {
            throw new LogicException('Cannot derive deposit net from zero opportunity gross.');
        }

        $exact = bcdiv(bcmul((string) $fixedGross, (string) $fullSubtotal, 10), (string) $fullGross, 10);

        return (int) bcadd($exact, '0.5', 0);
    }

    /**
     * @param  list<FrozenInvoiceTaxRowData>  $fullTaxRows
     * @return list<FrozenInvoiceTaxRowData>
     */
    private function scaleTaxRowsProportionally(array $fullTaxRows, int $depositNet, int $fullSubtotal): array
    {
        if ($fullTaxRows === []) {
            return [];
        }

        $ratio = bcdiv((string) $depositNet, (string) $fullSubtotal, 10);
        $fullTaxSum = array_sum(array_map(
            fn (FrozenInvoiceTaxRowData $row): int => $row->tax_amount,
            $fullTaxRows,
        ));

        $targetTaxTotal = $fullTaxSum === 0
            ? 0
            : $this->scaleAmount($fullTaxSum, $ratio);

        $scaled = [];
        $allocatedTax = 0;
        $allocatedTaxable = 0;
        $lastIndex = count($fullTaxRows) - 1;

        foreach ($fullTaxRows as $index => $row) {
            $isLast = $index === $lastIndex;

            if ($isLast) {
                $taxAmount = $targetTaxTotal - $allocatedTax;
                $taxableAmount = $depositNet - $allocatedTaxable;
            } else {
                $taxAmount = $this->scaleAmount($row->tax_amount, $ratio);
                $taxableAmount = $this->scaleAmount($row->taxable_amount, $ratio);
                $allocatedTax += $taxAmount;
                $allocatedTaxable += $taxableAmount;
            }

            $scaled[] = new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: $row->product_tax_class_key,
                taxable_amount: $taxableAmount,
                tax_amount: $taxAmount,
                rate_percentage: $row->rate_percentage,
                tax_rate_name: $row->tax_rate_name,
                tax_rate_id: $row->tax_rate_id,
                tax_rule_id: $row->tax_rule_id,
                result_type: $row->result_type,
                legal_text: $row->legal_text,
                zone_tax_rule_id: $row->zone_tax_rule_id,
            );
        }

        return $scaled;
    }

    /**
     * @param  list<FrozenInvoiceTaxRowData>  $fullTaxRows
     * @return list<FrozenInvoiceTaxRowData>
     */
    private function mirrorFullTaxRows(array $fullTaxRows): array
    {
        return array_map(
            fn (FrozenInvoiceTaxRowData $row): FrozenInvoiceTaxRowData => new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: $row->product_tax_class_key,
                taxable_amount: $row->taxable_amount,
                tax_amount: $row->tax_amount,
                rate_percentage: $row->rate_percentage,
                tax_rate_name: $row->tax_rate_name,
                tax_rate_id: $row->tax_rate_id,
                tax_rule_id: $row->tax_rule_id,
                result_type: $row->result_type,
                legal_text: $row->legal_text,
                zone_tax_rule_id: $row->zone_tax_rule_id,
            ),
            $fullTaxRows,
        );
    }

    /**
     * @param  list<FrozenInvoiceTaxRowData>  $taxRows
     */
    private function resolveLineTaxTreatment(array $taxRows): string
    {
        if ($taxRows === []) {
            return TaxResultType::Standard->value;
        }

        foreach ($taxRows as $row) {
            if ($row->tax_amount > 0) {
                return $row->result_type;
            }
        }

        return $taxRows[0]->result_type;
    }

    /**
     * @param  list<FrozenInvoiceTaxRowData>  $taxRows
     */
    private function assertPennyReconciliation(int $lineTax, array $taxRows): void
    {
        $groupTaxSum = array_sum(array_map(
            fn (FrozenInvoiceTaxRowData $row): int => $row->tax_amount,
            $taxRows,
        ));

        if ($lineTax !== $groupTaxSum) {
            throw new LogicException(sprintf(
                'Deposit tax penny reconciliation failed: line tax (%d) !== group tax sum (%d).',
                $lineTax,
                $groupTaxSum,
            ));
        }
    }

    private function scaleAmount(int $amount, string $ratio): int
    {
        if ($amount === 0) {
            return 0;
        }

        $scaled = bcmul((string) $amount, $ratio, 4);

        return (int) bcadd($scaled, $amount < 0 ? '-0.5' : '0.5', 0);
    }
}

<?php

namespace App\Services\Invoices;

use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Models\OpportunityItem;
use App\Services\Shortages\MoneyApportionment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Derives partial-quantity invoice lines and tax rows from a full frozen snapshot.
 *
 * Amounts scale proportionally from frozen line totals (quantity-ratio against the
 * opportunity line's total) — never recomputes price × qty. Tax scales through the
 * frozen rate groups (P35/P34 proportional machinery precedent).
 *
 * INVARIANT: Σ(line tax) ≡ Σ(group tax) to the penny.
 */
class PartialQuantityInvoiceCalculator
{
    public function __construct(
        private readonly MoneyApportionment $moneyApportionment,
    ) {}

    /**
     * @param  list<FrozenInvoiceLineData>  $fullLines
     * @param  list<FrozenInvoiceTaxRowData>  $fullTaxRows
     * @param  list<array{opportunity_item_id: int, quantity: string}>|null  $selections  null = all remaining
     * @param  Collection<int, OpportunityItem>  $lockedItems
     * @return array{
     *     lines: list<FrozenInvoiceLineData>,
     *     tax_rows: list<FrozenInvoiceTaxRowData>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int,
     *     quantity_updates: list<array{opportunity_item_id: int, quantity: string}>,
     *     mark_opportunity_invoiced: bool,
     * }
     */
    public function calculate(
        array $fullLines,
        array $fullTaxRows,
        int $fullSubtotal,
        int $fullTaxTotal,
        ?array $selections,
        Collection $lockedItems,
    ): array {
        $resolvedSelections = $this->resolveSelections($selections, $lockedItems);

        if ($resolvedSelections === []) {
            throw ValidationException::withMessages([
                'lines' => ['No remaining quantity is available to invoice.'],
            ]);
        }

        $qtyByItemId = [];

        foreach ($resolvedSelections as $selection) {
            $qtyByItemId[$selection['opportunity_item_id']] = $selection['quantity'];
        }

        $invoiceableItems = $lockedItems->filter(
            fn (OpportunityItem $item): bool => ! $item->is_optional && $item->item_type->isPriceable(),
        );

        $allItemsFullyInvoiced = $invoiceableItems->isNotEmpty()
            && $invoiceableItems->every(function (OpportunityItem $item) use ($qtyByItemId): bool {
                $after = bcadd((string) $item->invoiced_quantity, $qtyByItemId[$item->id] ?? '0', 2);

                return bccomp($after, (string) $item->quantity, 2) === 0;
            });

        $includeCosts = $allItemsFullyInvoiced;

        $headlineNetByLineKey = $this->resolveHeadlineNetByLineKey(
            $fullLines,
            $fullSubtotal,
            $includeCosts,
        );

        $scaledLines = [];
        $partialSubtotal = 0;
        $partialLineTax = 0;

        foreach ($fullLines as $line) {
            if ($line->source_type === 'cost') {
                if (! $includeCosts) {
                    continue;
                }

                $headlineNet = $headlineNetByLineKey[$line->line_key] ?? $line->net_total;
                $scaled = $this->scaleLine($line, $line->quantity, '1', $headlineNet);
                $scaledLines[] = $scaled;
                $partialSubtotal += $scaled->net_total;
                $partialLineTax += $scaled->tax;

                continue;
            }

            $itemId = $line->opportunity_item_id;

            if ($itemId === null || ! isset($qtyByItemId[$itemId])) {
                continue;
            }

            $item = $lockedItems->get($itemId);

            if ($item === null) {
                continue;
            }

            $fullLineQty = (string) $item->quantity;
            $invoicedQty = $qtyByItemId[$itemId];
            $ratio = bcdiv($invoicedQty, $fullLineQty, 10);
            $headlineNet = $headlineNetByLineKey[$line->line_key] ?? $line->net_total;

            $scaled = $this->scaleLine($line, $invoicedQty, $ratio, $headlineNet);
            $scaledLines[] = $scaled;
            $partialSubtotal += $scaled->net_total;
            $partialLineTax += $scaled->tax;
        }

        if ($scaledLines === []) {
            throw ValidationException::withMessages([
                'lines' => ['No invoice lines could be derived from the selection.'],
            ]);
        }

        $taxRows = $includeCosts && $partialSubtotal === $fullSubtotal
            ? $fullTaxRows
            : $this->scaleTaxRowsProportionally($fullTaxRows, $partialSubtotal, $partialLineTax, $fullSubtotal);

        $taxTotal = array_sum(array_map(
            fn (FrozenInvoiceTaxRowData $row): int => $row->tax_amount,
            $taxRows,
        ));

        if ($partialLineTax !== $taxTotal) {
            throw new LogicException(sprintf(
                'Partial invoice tax reconciliation failed: line tax (%d) !== group tax (%d).',
                $partialLineTax,
                $taxTotal,
            ));
        }

        $quantityUpdates = array_map(
            fn (array $selection): array => [
                'opportunity_item_id' => $selection['opportunity_item_id'],
                'quantity' => $selection['quantity'],
            ],
            $resolvedSelections,
        );

        return [
            'lines' => $scaledLines,
            'tax_rows' => $taxRows,
            'subtotal' => $partialSubtotal,
            'tax_total' => $taxTotal,
            'total' => $partialSubtotal + $taxTotal,
            'quantity_updates' => $quantityUpdates,
            'mark_opportunity_invoiced' => $includeCosts,
        ];
    }

    /**
     * @param  list<array{opportunity_item_id: int, quantity?: string|null}>|null  $selections
     * @param  Collection<int, OpportunityItem>  $lockedItems
     * @return list<array{opportunity_item_id: int, quantity: string}>
     */
    private function resolveSelections(?array $selections, Collection $lockedItems): array
    {
        $invoiceableItems = $lockedItems->filter(
            fn (OpportunityItem $item): bool => ! $item->is_optional && $item->item_type->isPriceable(),
        );

        if ($selections === null) {
            $resolved = [];

            foreach ($invoiceableItems as $item) {
                $remaining = bcsub((string) $item->quantity, (string) $item->invoiced_quantity, 2);

                if (bccomp($remaining, '0', 2) <= 0) {
                    continue;
                }

                $resolved[] = [
                    'opportunity_item_id' => (int) $item->id,
                    'quantity' => $remaining,
                ];
            }

            return $resolved;
        }

        if ($selections === []) {
            throw ValidationException::withMessages([
                'lines' => ['At least one opportunity line must be selected for partial invoicing.'],
            ]);
        }

        $resolved = [];

        foreach ($selections as $selection) {
            $itemId = (int) $selection['opportunity_item_id'];
            $qty = isset($selection['quantity']) ? (string) $selection['quantity'] : null;

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'lines' => ['Each line selection must include a valid opportunity_item_id.'],
                ]);
            }

            $item = $invoiceableItems->get($itemId) ?? $lockedItems->get($itemId);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Opportunity item [{$itemId}] is not available for invoicing."],
                ]);
            }

            if ($qty === null || bccomp($qty, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'lines' => ["Quantity must be greater than zero for opportunity item [{$itemId}]."],
                ]);
            }

            $currentInvoiced = (string) $item->invoiced_quantity;
            $lineQty = (string) $item->quantity;
            $newTotal = bcadd($currentInvoiced, $qty, 2);

            if (bccomp($newTotal, $lineQty, 2) > 0) {
                throw ValidationException::withMessages([
                    'lines' => ["Invoiced quantity would exceed the line quantity for opportunity item [{$itemId}]."],
                ]);
            }

            $resolved[] = [
                'opportunity_item_id' => $itemId,
                'quantity' => $qty,
            ];
        }

        return $resolved;
    }

    /**
     * @param  list<FrozenInvoiceLineData>  $fullLines
     * @return array<string, int>
     */
    private function resolveHeadlineNetByLineKey(
        array $fullLines,
        int $fullSubtotal,
        bool $includeCosts,
    ): array {
        /** @var array<string, int> $itemWeights */
        $itemWeights = [];
        /** @var array<string, int> $costNets */
        $costNets = [];
        $costNetSumAll = 0;

        foreach ($fullLines as $line) {
            if ($line->source_type === 'cost') {
                $costNetSumAll += $line->net_total;

                if ($includeCosts) {
                    $costNets[$line->line_key] = $line->net_total;
                }

                continue;
            }

            $itemWeights[$line->line_key] = $line->net_total;
        }

        $itemWeightSum = array_sum($itemWeights);
        $costSum = array_sum($costNets);
        $itemTarget = $includeCosts
            ? $fullSubtotal - $costSum
            : $fullSubtotal - $costNetSumAll;

        if ($itemWeights === [] || $itemWeightSum === $itemTarget) {
            return array_merge($itemWeights, $costNets);
        }

        return array_merge(
            $this->moneyApportionment->apportion($itemWeights, $itemTarget),
            $costNets,
        );
    }

    private function scaleLine(
        FrozenInvoiceLineData $line,
        string $invoicedQty,
        string $ratio,
        int $headlineNet,
    ): FrozenInvoiceLineData {
        $lineInvoicedQty = $line->source_type === 'item' ? $invoicedQty : null;
        $headlineRatio = $line->net_total !== 0
            ? bcdiv((string) $headlineNet, (string) $line->net_total, 10)
            : '1';

        if (bccomp($ratio, '1', 10) === 0) {
            return new FrozenInvoiceLineData(
                invoice_item_id: $line->invoice_item_id,
                line_key: $line->line_key,
                source_type: $line->source_type,
                description: $line->description,
                quantity: $line->source_type === 'item' ? $invoicedQty : $line->quantity,
                unit_price: $line->unit_price,
                discount: $this->scaleAmount($line->discount, $headlineRatio),
                net_total: $headlineNet,
                tax: $line->tax,
                product_tax_class_id: $line->product_tax_class_id,
                tax_treatment: $line->tax_treatment,
                opportunity_item_id: $line->opportunity_item_id,
                opportunity_cost_id: $line->opportunity_cost_id,
                product_id: $line->product_id,
                surcharge_id: $line->surcharge_id,
                section_item_id: $line->section_item_id,
                sort_order: $line->sort_order,
                invoiced_quantity: $lineInvoicedQty,
            );
        }

        return new FrozenInvoiceLineData(
            invoice_item_id: $line->invoice_item_id,
            line_key: $line->line_key,
            source_type: $line->source_type,
            description: $line->description,
            quantity: $line->source_type === 'item' ? $invoicedQty : $line->quantity,
            unit_price: $line->unit_price,
            discount: $this->scaleAmount($this->scaleAmount($line->discount, $headlineRatio), $ratio),
            net_total: $this->scaleAmount($headlineNet, $ratio),
            tax: $this->scaleAmount($line->tax, $ratio),
            product_tax_class_id: $line->product_tax_class_id,
            tax_treatment: $line->tax_treatment,
            opportunity_item_id: $line->opportunity_item_id,
            opportunity_cost_id: $line->opportunity_cost_id,
            product_id: $line->product_id,
            surcharge_id: $line->surcharge_id,
            section_item_id: $line->section_item_id,
            sort_order: $line->sort_order,
            invoiced_quantity: $lineInvoicedQty,
        );
    }

    /**
     * @param  list<FrozenInvoiceTaxRowData>  $fullTaxRows
     * @return list<FrozenInvoiceTaxRowData>
     */
    private function scaleTaxRowsProportionally(
        array $fullTaxRows,
        int $partialSubtotal,
        int $partialLineTax,
        int $fullSubtotal,
    ): array {
        if ($fullTaxRows === []) {
            if ($partialLineTax !== 0) {
                throw new LogicException('Partial invoice has line tax but no tax rows.');
            }

            return [];
        }

        if ($fullSubtotal === 0) {
            return $fullTaxRows;
        }

        $ratio = bcdiv((string) $partialSubtotal, (string) $fullSubtotal, 10);
        $scaled = [];
        $allocatedTax = 0;
        $allocatedTaxable = 0;
        $lastIndex = count($fullTaxRows) - 1;

        foreach ($fullTaxRows as $index => $row) {
            $isLast = $index === $lastIndex;

            if ($isLast) {
                $taxAmount = $partialLineTax - $allocatedTax;
                $taxableAmount = $partialSubtotal - $allocatedTaxable;
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

    private function scaleAmount(int $amount, string $ratio): int
    {
        if ($amount === 0) {
            return 0;
        }

        $scaled = bcmul((string) $amount, $ratio, 4);

        return (int) bcadd($scaled, $amount < 0 ? '-0.5' : '0.5', 0);
    }
}

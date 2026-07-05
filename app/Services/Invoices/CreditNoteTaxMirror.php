<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTax;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Builds negated credit note line and tax snapshots from frozen invoice rows.
 *
 * NEVER re-resolves tax through the tax engine — amounts are mirrored (and
 * proportionally scaled for partial credits) from the original invoice
 * projection. INVARIANT: Σ(CN line tax) ≡ Σ(CN group tax) to the penny.
 */
class CreditNoteTaxMirror
{
    /**
     * @param  list<array{invoice_item_id: int, quantity?: string|null}>|null  $selections  null = full credit
     * @return array{
     *     line_items: list<array<string, mixed>>,
     *     tax_rows: list<array<string, mixed>>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int,
     *     credit_amount: int,
     * }
     */
    public function mirror(Invoice $invoice, ?array $selections): array
    {
        $invoice->loadMissing(['items', 'taxes']);

        $items = $invoice->items->sortBy('sort_order')->values();
        $taxes = $invoice->taxes->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'invoice' => ['Cannot issue a credit note against an invoice with no line items.'],
            ]);
        }

        $isFullCredit = $selections === null;

        /** @var Collection<int, InvoiceItem> $itemById */
        $itemById = $items->keyBy('id');

        $resolvedSelections = $this->resolveSelections($items, $selections);

        $linePayloads = [];
        $creditedByItemId = [];

        foreach ($resolvedSelections as $selection) {
            $item = $itemById->get($selection['invoice_item_id']);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Invoice item [{$selection['invoice_item_id']}] does not belong to this invoice."],
                ]);
            }

            $creditedQty = $selection['quantity'];
            $originalQty = (string) $item->quantity;

            if (bccomp($creditedQty, $originalQty, 2) > 0) {
                throw ValidationException::withMessages([
                    'lines' => ["Credit quantity exceeds the original quantity for item [{$item->id}]."],
                ]);
            }

            $linePayload = $this->negateLine($item, $creditedQty, $originalQty);
            $linePayloads[] = $linePayload;
            $creditedByItemId[$item->id] = ($creditedByItemId[$item->id] ?? 0) + abs((int) $linePayload['total']) + abs((int) $linePayload['tax']);
        }

        $subtotal = array_sum(array_column($linePayloads, 'total'));
        $lineTaxTotal = array_sum(array_column($linePayloads, 'tax'));

        $taxPayloads = $isFullCredit
            ? $this->negateAllTaxRows($taxes)
            : $this->negateTaxRowsProportionally($taxes, $subtotal, $lineTaxTotal);

        $taxTotal = array_sum(array_column($taxPayloads, 'tax_amount'));
        $total = $subtotal + $taxTotal;
        $creditAmount = abs($total);

        if ($creditAmount <= 0) {
            throw ValidationException::withMessages([
                'lines' => ['Credit note total must be greater than zero.'],
            ]);
        }

        if ($lineTaxTotal !== $taxTotal) {
            throw new LogicException(
                "Credit note tax reconciliation failed: line tax [{$lineTaxTotal}] ≠ group tax [{$taxTotal}].",
            );
        }

        return [
            'line_items' => $linePayloads,
            'tax_rows' => $taxPayloads,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'credit_amount' => $creditAmount,
        ];
    }

    /**
     * @param  Collection<int, InvoiceItem>  $items
     * @param  list<array{invoice_item_id: int, quantity?: string|null}>|null  $selections
     * @return list<array{invoice_item_id: int, quantity: string}>
     */
    private function resolveSelections(Collection $items, ?array $selections): array
    {
        if ($selections === null) {
            return $items->map(fn (InvoiceItem $item): array => [
                'invoice_item_id' => $item->id,
                'quantity' => (string) $item->quantity,
            ])->all();
        }

        if ($selections === []) {
            throw ValidationException::withMessages([
                'lines' => ['At least one invoice line must be selected for a partial credit note.'],
            ]);
        }

        $resolved = [];
        $seenItemIds = [];

        foreach ($selections as $selection) {
            $itemId = (int) $selection['invoice_item_id'];
            $qty = $selection['quantity'] ?? null;

            if ($itemId <= 0) {
                throw ValidationException::withMessages([
                    'lines' => ['Each credit line selection must include a valid invoice_item_id.'],
                ]);
            }

            if (isset($seenItemIds[$itemId])) {
                throw ValidationException::withMessages([
                    'lines' => ["Invoice item [{$itemId}] appears more than once in the credit line selection."],
                ]);
            }

            $seenItemIds[$itemId] = true;

            $item = $items->firstWhere('id', $itemId);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Invoice item [{$itemId}] does not belong to this invoice."],
                ]);
            }

            $resolved[] = [
                'invoice_item_id' => $itemId,
                'quantity' => $qty !== null ? (string) $qty : (string) $item->quantity,
            ];
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function negateLine(InvoiceItem $item, string $creditedQty, string $originalQty): array
    {
        if ($creditedQty === $originalQty) {
            return [
                'invoice_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => $creditedQty,
                'unit_price' => -$item->unit_price,
                'discount' => -$item->discount,
                'tax' => -$item->tax,
                'total' => -$item->total,
                'tax_treatment' => $item->tax_treatment,
                'product_tax_class_id' => $item->product_tax_class_id,
                'sort_order' => $item->sort_order,
            ];
        }

        $ratio = bcdiv($creditedQty, $originalQty, 10);

        return [
            'invoice_item_id' => $item->id,
            'description' => $item->description,
            'quantity' => $creditedQty,
            'unit_price' => -$item->unit_price,
            'discount' => $this->scaleAmount(-$item->discount, $ratio),
            'tax' => $this->scaleAmount(-$item->tax, $ratio),
            'total' => $this->scaleAmount(-$item->total, $ratio),
            'tax_treatment' => $item->tax_treatment,
            'product_tax_class_id' => $item->product_tax_class_id,
            'sort_order' => $item->sort_order,
        ];
    }

    /**
     * @param  Collection<int, InvoiceTax>  $taxes
     * @return list<array<string, mixed>>
     */
    private function negateAllTaxRows(Collection $taxes): array
    {
        return $taxes->map(fn (InvoiceTax $row): array => [
            'invoice_tax_id' => $row->id,
            'tax_rate_id' => $row->tax_rate_id,
            'taxable_amount' => -$row->taxable_amount,
            'tax_amount' => -$row->tax_amount,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, InvoiceTax>  $taxes
     * @return list<array<string, mixed>>
     */
    private function negateTaxRowsProportionally(Collection $taxes, int $creditedSubtotal, int $creditedLineTaxTotal): array
    {
        if ($taxes->isEmpty()) {
            if ($creditedLineTaxTotal !== 0) {
                throw new LogicException('Credit note has line tax but no invoice tax rows.');
            }

            return [];
        }

        $originalTaxableTotal = $taxes->sum('taxable_amount');
        $originalTaxTotal = $taxes->sum('tax_amount');

        if ($originalTaxTotal === 0) {
            return $this->negateAllTaxRows($taxes);
        }

        $payloads = [];
        $allocatedTax = 0;
        $allocatedTaxable = 0;
        $lastIndex = $taxes->count() - 1;

        foreach ($taxes->values() as $index => $row) {
            $isLast = $index === $lastIndex;

            if ($isLast) {
                $taxAmount = $creditedLineTaxTotal - $allocatedTax;
                $taxableAmount = $creditedSubtotal - $allocatedTaxable;
            } else {
                $taxAmount = $this->scaleAmount(-$row->tax_amount, bcdiv((string) abs($creditedLineTaxTotal), (string) $originalTaxTotal, 10));
                $taxableAmount = $originalTaxableTotal > 0
                    ? $this->scaleAmount(-$row->taxable_amount, bcdiv((string) abs($creditedSubtotal), (string) $originalTaxableTotal, 10))
                    : 0;
                $allocatedTax += $taxAmount;
                $allocatedTaxable += $taxableAmount;
            }

            $payloads[] = [
                'invoice_tax_id' => $row->id,
                'tax_rate_id' => $row->tax_rate_id,
                'taxable_amount' => $taxableAmount,
                'tax_amount' => $taxAmount,
            ];
        }

        return $payloads;
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

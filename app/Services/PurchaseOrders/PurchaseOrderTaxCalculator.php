<?php

namespace App\Services\PurchaseOrders;

use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Models\Member;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\ValueObjects\Tax\DocumentTaxSummary;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use DateTimeInterface;
use LogicException;

/**
 * Resolves purchase-axis tax for PO lines via {@see DocumentTaxBreakdown}.
 *
 * Expected tax (full order quantities) is computed at create/update time.
 * Actual tax (received quantities) is computed at receipt time when the PO
 * becomes fully received; {@see PurchaseOrderReceiptRecorded} carries the
 * fire-time actual figures.
 */
class PurchaseOrderTaxCalculator
{
    public function __construct(
        private readonly DocumentTaxBreakdown $documentTaxBreakdown,
        private readonly TaxResolutionContextHolder $taxResolutionContextHolder,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function applyExpectedLineTax(
        int $supplierId,
        int $storeId,
        string $currencyCode,
        array $lines,
        ?DateTimeInterface $effectiveAt = null,
    ): array {
        $summary = $this->summarizeLines(
            supplierId: $supplierId,
            storeId: $storeId,
            currencyCode: $currencyCode,
            lines: $lines,
            quantityResolver: fn (array $line): string => (string) $line['quantity'],
            effectiveAt: $effectiveAt,
        );

        return $this->mergeLineTax($lines, $summary);
    }

    /**
     * @param  list<array{purchase_order_item_id: int, received_quantity: string, unit_cost: int, product_tax_class_id: ?int, product_id: ?int}>  $receiptLines
     * @return array{
     *     lines: list<array{purchase_order_item_id: int, tax: int, total: int, received_quantity: string}>,
     *     subtotal: int,
     *     tax_total: int,
     *     rounding_adjustment: int,
     *     total: int,
     * }
     */
    public function actualReceiptTotals(
        PurchaseOrder $purchaseOrder,
        array $receiptLines,
        ?DateTimeInterface $effectiveAt = null,
    ): array {
        $currency = strtoupper($purchaseOrder->currency_code);
        $summary = $this->summarizeLines(
            supplierId: (int) $purchaseOrder->supplier_id,
            storeId: (int) $purchaseOrder->store_id,
            currencyCode: $currency,
            lines: $receiptLines,
            quantityResolver: fn (array $line): string => (string) $line['received_quantity'],
            effectiveAt: $effectiveAt,
        );

        $taxByKey = [];
        foreach ($summary->lineAllocations as $allocation) {
            $taxByKey[$allocation->lineKey] = $allocation->taxMinor;
        }

        $subtotal = Money::zero($currency);
        $lineResults = [];

        foreach ($receiptLines as $line) {
            $lineKey = (string) $line['purchase_order_item_id'];
            $quantity = bcadd((string) $line['received_quantity'], '0', 2);
            $unitCost = (int) $line['unit_cost'];
            $lineTax = array_key_exists($lineKey, $taxByKey) ? $taxByKey[$lineKey] : 0;

            $lineNet = Money::ofMinor($unitCost, $currency)
                ->multipliedBy($quantity, RoundingMode::HALF_UP);
            $lineTotal = $lineNet->plus(Money::ofMinor($lineTax, $currency));

            $subtotal = $subtotal->plus($lineNet);

            $lineResults[] = [
                'purchase_order_item_id' => (int) $line['purchase_order_item_id'],
                'received_quantity' => $quantity,
                'tax' => $lineTax,
                'total' => $lineTotal->getMinorAmount()->toInt(),
            ];
        }

        $lineTaxSum = array_sum(array_column($lineResults, 'tax'));
        $roundingAdjustment = $summary->totalTax - $lineTaxSum;
        $taxTotal = $summary->totalTax;
        $total = $subtotal
            ->plus(Money::ofMinor($taxTotal, $currency))
            ->plus(Money::ofMinor($roundingAdjustment, $currency));

        return [
            'lines' => $lineResults,
            'subtotal' => $subtotal->getMinorAmount()->toInt(),
            'tax_total' => $taxTotal,
            'rounding_adjustment' => $roundingAdjustment,
            'total' => $total->getMinorAmount()->toInt(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  callable(array<string, mixed>): string  $quantityResolver
     */
    private function summarizeLines(
        int $supplierId,
        int $storeId,
        string $currencyCode,
        array $lines,
        callable $quantityResolver,
        ?DateTimeInterface $effectiveAt,
    ): DocumentTaxSummary {
        $supplier = Member::query()->find($supplierId);
        $store = Store::query()->find($storeId);

        if ($supplier === null || $store === null) {
            return new DocumentTaxSummary(groups: [], lineAllocations: [], totalNet: 0, totalTax: 0);
        }

        $organisationTaxClassId = $supplier->purchase_tax_class_id;

        if ($organisationTaxClassId === null) {
            return new DocumentTaxSummary(groups: [], lineAllocations: [], totalNet: 0, totalTax: 0);
        }

        $productIds = array_values(array_filter(array_map(
            fn (array $line): ?int => isset($line['product_id']) ? (int) $line['product_id'] : null,
            $lines,
        )));

        $productsById = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $taxLineInputs = [];

        foreach ($lines as $line) {
            $quantity = bcadd($quantityResolver($line), '0', 2);

            if (bccomp($quantity, '0', 2) <= 0) {
                continue;
            }

            $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
            $productTaxClassId = $line['product_tax_class_id'] ?? null;
            $productTaxCategoryId = $line['purchase_tax_category_id'] ?? null;

            if ($productId !== null) {
                $product = $productsById->get($productId);

                if ($productTaxClassId === null) {
                    $productTaxClassId = $product?->purchase_tax_class_id;
                }

                if ($productTaxCategoryId === null) {
                    $productTaxCategoryId = $product?->purchase_tax_category_id;
                }
            }

            if ($productTaxClassId === null) {
                continue;
            }

            $unitCost = (int) ($line['unit_cost'] ?? 0);
            $netMinor = Money::ofMinor($unitCost, strtoupper($currencyCode))
                ->multipliedBy($quantity, RoundingMode::HALF_UP)
                ->getMinorAmount()
                ->toInt();

            $lineKey = isset($line['purchase_order_item_id'])
                ? (string) $line['purchase_order_item_id']
                : spl_object_hash((object) $line);

            $taxLineInputs[] = new DocumentTaxLineInput(
                lineKey: $lineKey,
                netMinor: $netMinor,
                productTaxClassId: (int) $productTaxClassId,
                productTaxCategoryId: $productTaxCategoryId !== null ? (int) $productTaxCategoryId : null,
                quantity: $quantity,
            );
        }

        if ($taxLineInputs === []) {
            return new DocumentTaxSummary(groups: [], lineAllocations: [], totalNet: 0, totalTax: 0);
        }

        $context = TaxResolutionContext::fromPurchaseOrder($supplier, $store);

        return $this->taxResolutionContextHolder->using(
            $context,
            fn (): DocumentTaxSummary => $this->documentTaxBreakdown->summarize(new DocumentTaxRequest(
                lines: $taxLineInputs,
                currencyCode: strtoupper($currencyCode),
                organisationTaxClassId: $organisationTaxClassId,
                effectiveAt: $effectiveAt,
                pricesIncludeTax: false,
            )),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function mergeLineTax(array $lines, DocumentTaxSummary $summary): array
    {
        $taxByKey = [];
        foreach ($summary->lineAllocations as $allocation) {
            $taxByKey[$allocation->lineKey] = $allocation->taxMinor;
        }

        return array_map(function (array $line) use ($taxByKey): array {
            if (! isset($line['purchase_order_item_id'])) {
                return $line;
            }

            $lineKey = (string) $line['purchase_order_item_id'];
            $line['tax'] = $taxByKey[$lineKey] ?? (int) ($line['tax'] ?? 0);

            if (($line['product_tax_class_id'] ?? null) === null && isset($line['product_id'])) {
                $product = Product::query()->find((int) $line['product_id']);
                $line['product_tax_class_id'] = $product?->purchase_tax_class_id;
            }

            return $line;
        }, $lines);
    }

    /**
     * @param  list<int>  $lineTotals
     *
     * @throws LogicException
     */
    public function assertPennyReconciliation(int $subtotal, int $taxTotal, int $roundingAdjustment, int $total, array $lineTotals): void
    {
        $computed = $subtotal + $taxTotal + $roundingAdjustment;

        if ($computed !== $total) {
            throw new LogicException(
                "Purchase order total mismatch: {$subtotal} + {$taxTotal} + {$roundingAdjustment} !== {$total}",
            );
        }

        $lineSum = array_sum($lineTotals);

        if ($lineSum + $roundingAdjustment !== $total) {
            throw new LogicException(
                'Purchase order line totals do not reconcile with header total.',
            );
        }
    }
}

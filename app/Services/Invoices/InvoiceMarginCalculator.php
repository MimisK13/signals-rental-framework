<?php

namespace App\Services\Invoices;

use App\Data\Invoices\InvoiceLineMarginResult;
use App\Data\Invoices\InvoiceMarginResult;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\ProductSupplierCost;
use App\Services\Pricing\QuantityMinorArithmetic;
use App\Services\Pricing\RoundingStrategy;
use App\Services\Shortages\SubHireCostCalculator;
use App\Services\Shortages\SubHireSupplierCostQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives invoice margin from frozen invoice lines, product supplier costs, and
 * opportunity-level costs (including apportioned virtual-stock intake costs).
 *
 * Per-line basis: preferred {@see ProductSupplierCost} × invoiced quantity for
 * primary product lines; opportunity cost net for matched cost invoice lines.
 * Opportunity costs without item linkage that are not represented on the invoice
 * are proportioned at invoice level by net-revenue share.
 */
class InvoiceMarginCalculator
{
    public function __construct(
        private readonly SubHireSupplierCostQuery $supplierCostQuery,
        private readonly SubHireCostCalculator $subHireCostCalculator,
        private readonly QuantityMinorArithmetic $quantityMinor,
        private readonly RoundingStrategy $roundingStrategy,
    ) {}

    public function calculate(Invoice $invoice): InvoiceMarginResult
    {
        $invoice->loadMissing([
            'items.opportunityItem',
            'opportunity.costs',
            'opportunity.items',
            'store',
        ]);

        $currency = $invoice->currency_code !== '' ? $invoice->currency_code : 'GBP';
        $opportunity = $invoice->opportunity;
        $opportunityCosts = $opportunity !== null
            ? $opportunity->costs->filter(fn (OpportunityCost $cost): bool => ! $cost->is_optional)
            : collect();

        /** @var Collection<int, OpportunityItem> $opportunityItemsById */
        $opportunityItemsById = $opportunity !== null
            ? $opportunity->items->keyBy('id')
            : collect();

        $matchedCostIds = [];
        $lineResults = [];
        $lineCostTotal = 0;

        foreach ($invoice->items->sortBy('sort_order') as $item) {
            $revenue = (int) $item->total;
            $basis = $this->resolveLineCostBasis(
                item: $item,
                invoice: $invoice,
                opportunity: $opportunity,
                opportunityCosts: $opportunityCosts,
                opportunityItemsById: $opportunityItemsById,
                currency: $currency,
                matchedCostIds: $matchedCostIds,
            );

            if ($basis !== null) {
                $lineCostTotal += $basis;
            }

            $lineResults[$item->id] = $this->buildLineResult(
                itemId: (int) $item->id,
                revenueMinor: $revenue,
                costBasisMinor: $basis,
                currency: $currency,
            );
        }

        $unattributedCostTotal = $this->sumOpportunityCostNets(
            costs: $opportunityCosts->reject(
                fn (OpportunityCost $cost): bool => in_array($cost->id, $matchedCostIds, true),
            ),
            opportunity: $opportunity,
            currency: $currency,
        );

        $revenueShare = $this->resolveRevenueShare($invoice, $opportunity);
        $scaledUnattributed = $this->scaleMinorAmount($unattributedCostTotal, $revenueShare, $currency);

        $netRevenue = (int) $invoice->subtotal;
        $attributableCost = $lineCostTotal + $scaledUnattributed;
        $margin = $netRevenue - $attributableCost;

        return new InvoiceMarginResult(
            net_revenue_minor: $netRevenue,
            attributable_cost_minor: $attributableCost,
            margin_minor: $margin,
            margin_percent: $this->formatPercent($margin, $netRevenue),
            lines: $lineResults,
        );
    }

    /**
     * @param  Collection<int, OpportunityCost>  $opportunityCosts
     * @param  Collection<int, OpportunityItem>  $opportunityItemsById
     * @param  list<int>  $matchedCostIds
     */
    private function resolveLineCostBasis(
        InvoiceItem $item,
        Invoice $invoice,
        ?Opportunity $opportunity,
        Collection $opportunityCosts,
        Collection $opportunityItemsById,
        string $currency,
        array &$matchedCostIds,
    ): ?int {
        $matchedCost = $this->matchOpportunityCostLine($item, $opportunityCosts, $matchedCostIds);

        if ($matchedCost !== null) {
            $matchedCostIds[] = $matchedCost->id;

            return $this->opportunityCostNet($matchedCost, $opportunity, $currency);
        }

        if ($item->product_id === null || $item->opportunity_item_id === null) {
            return null;
        }

        $opportunityItem = $opportunityItemsById->get((int) $item->opportunity_item_id);

        if ($opportunityItem === null || ! $this->isPrimaryProductLine($item, $opportunityItem)) {
            return null;
        }

        $supplierCost = $this->resolvePreferredSupplierCost((int) $item->product_id, (int) $invoice->store_id);

        if ($supplierCost === null) {
            return null;
        }

        return $this->supplierCostBasis(
            supplierCost: $supplierCost,
            quantity: (string) ($item->invoiced_quantity ?? $item->quantity),
            opportunityItem: $opportunityItem,
            currency: $currency,
        );
    }

    /**
     * @param  Collection<int, OpportunityCost>  $opportunityCosts
     * @param  list<int>  $matchedCostIds
     */
    private function matchOpportunityCostLine(
        InvoiceItem $item,
        Collection $opportunityCosts,
        array $matchedCostIds,
    ): ?OpportunityCost {
        if ($item->product_id !== null || $item->opportunity_item_id !== null) {
            return null;
        }

        foreach ($opportunityCosts as $cost) {
            if (in_array($cost->id, $matchedCostIds, true)) {
                continue;
            }

            if ($cost->description !== $item->description) {
                continue;
            }

            if ((int) $cost->amount !== (int) $item->unit_price) {
                continue;
            }

            if (bccomp(
                $this->quantityMinor->normalizeQuantity((string) $cost->quantity),
                $this->quantityMinor->normalizeQuantity((string) $item->quantity),
                2,
            ) !== 0) {
                continue;
            }

            return $cost;
        }

        return null;
    }

    private function isPrimaryProductLine(InvoiceItem $item, OpportunityItem $opportunityItem): bool
    {
        return (int) $item->unit_price === (int) $opportunityItem->unit_price;
    }

    private function resolvePreferredSupplierCost(int $productId, int $storeId): ?ProductSupplierCost
    {
        return $this->supplierCostQuery->candidatesFor($productId, $storeId)->first();
    }

    private function supplierCostBasis(
        ProductSupplierCost $supplierCost,
        string $quantity,
        OpportunityItem $opportunityItem,
        string $currency,
    ): int {
        $normalizedQty = $this->quantityMinor->normalizeQuantity($quantity);

        if (bccomp($normalizedQty, '0', 2) <= 0) {
            return 0;
        }

        if (in_array($supplierCost->cost_basis, ['per_day', 'per_week'], true)
            && $opportunityItem->starts_at !== null
            && $opportunityItem->ends_at !== null) {
            $wholeQty = max(1, (int) bcadd($normalizedQty, '0', 0));

            return $this->subHireCostCalculator->estimatedTotal(
                $supplierCost,
                $wholeQty,
                Carbon::parse($opportunityItem->starts_at),
                Carbon::parse($opportunityItem->ends_at),
            );
        }

        return $this->quantityMinor->multiplyMinorByQuantity(
            (int) $supplierCost->cost,
            $normalizedQty,
            $currency,
        );
    }

    /**
     * @param  Collection<int, OpportunityCost>  $costs
     */
    private function sumOpportunityCostNets(
        Collection $costs,
        ?Opportunity $opportunity,
        string $currency,
    ): int {
        $total = 0;

        foreach ($costs as $cost) {
            $total += $this->opportunityCostNet($cost, $opportunity, $currency);
        }

        return $total;
    }

    private function opportunityCostNet(
        OpportunityCost $cost,
        ?Opportunity $opportunity,
        string $currency,
    ): int {
        return $this->quantityMinor->multiplyMinorByQuantity(
            (int) $cost->amount,
            (string) $cost->quantity,
            $cost->currency_code !== null && $cost->currency_code !== ''
                ? $cost->currency_code
                : $currency,
        );
    }

    private function resolveRevenueShare(Invoice $invoice, ?Opportunity $opportunity): string
    {
        if ($opportunity === null) {
            return '1';
        }

        $opportunityNet = (int) $opportunity->charge_total;

        if ($opportunityNet <= 0) {
            return '1';
        }

        return bcdiv((string) $invoice->subtotal, (string) $opportunityNet, 10);
    }

    private function scaleMinorAmount(int $amount, string $ratio, string $currency): int
    {
        if ($amount === 0) {
            return 0;
        }

        if ($ratio === '1' || bccomp($ratio, '1', 10) === 0) {
            return $amount;
        }

        $scaled = bcmul((string) $amount, $ratio, 6);

        return $this->roundingStrategy->roundMinorAmount($scaled, $currency);
    }

    private function buildLineResult(
        int $itemId,
        int $revenueMinor,
        ?int $costBasisMinor,
        string $currency,
    ): InvoiceLineMarginResult {
        if ($costBasisMinor === null) {
            return new InvoiceLineMarginResult(
                invoice_item_id: $itemId,
                has_basis: false,
                revenue_minor: $revenueMinor,
                cost_basis_minor: null,
                margin_minor: null,
                margin_percent: null,
            );
        }

        $margin = $revenueMinor - $costBasisMinor;

        return new InvoiceLineMarginResult(
            invoice_item_id: $itemId,
            has_basis: true,
            revenue_minor: $revenueMinor,
            cost_basis_minor: $costBasisMinor,
            margin_minor: $margin,
            margin_percent: $this->formatPercent($margin, $revenueMinor),
        );
    }

    private function formatPercent(int $part, int $whole): ?string
    {
        if ($whole === 0) {
            return null;
        }

        $percent = BigDecimal::of($part)
            ->dividedBy($whole, 8, RoundingMode::HALF_UP)
            ->multipliedBy(100)
            ->toScale(1, RoundingMode::HALF_UP);

        return rtrim(rtrim($percent->__toString(), '0'), '.').'%';
    }
}

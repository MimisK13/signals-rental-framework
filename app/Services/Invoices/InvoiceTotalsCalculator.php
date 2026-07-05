<?php

namespace App\Services\Invoices;

use App\Data\Invoices\FrozenInvoiceLineData;
use App\Data\Invoices\FrozenInvoiceTaxRowData;
use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\Enums\TaxResultType;
use App\Models\Opportunity;
use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\Tax\TaxPointDateResolver;
use App\Services\Tax\TaxResolutionContext;
use App\Services\Tax\TaxResolutionContextHolder;
use App\ValueObjects\Tax\DocumentTaxGroup;
use App\ValueObjects\Tax\DocumentTaxLineAllocation;
use App\ValueObjects\Tax\DocumentTaxSummary;
use DateTimeInterface;
use LogicException;

/**
 * Computes frozen invoice tax totals via {@see DocumentTaxBreakdown} using the
 * invoice currency and tax-point date resolved at fire-time.
 *
 * INVARIANT (R3): Σ(line taxes) ≡ Σ(rate-group taxes) to the penny.
 */
class InvoiceTotalsCalculator
{
    public function __construct(
        private readonly DocumentTaxBreakdown $documentTaxBreakdown,
        private readonly TaxPointDateResolver $taxPointDateResolver,
        private readonly TaxResolutionContextHolder $taxResolutionContextHolder,
    ) {}

    /**
     * @param  list<FrozenInvoiceLineData>  $displayLines
     * @param  list<DocumentTaxLineInput>  $taxLines
     * @return array{
     *     lines: list<FrozenInvoiceLineData>,
     *     tax_rows: list<FrozenInvoiceTaxRowData>,
     *     subtotal: int,
     *     tax_total: int,
     *     total: int,
     * }
     */
    public function calculate(
        Opportunity $opportunity,
        array $displayLines,
        array $taxLines,
        int $netHeadline,
        DateTimeInterface $invoiceDate,
    ): array {
        $currency = $this->currencyFor($opportunity);
        $taxPointDate = $this->taxPointDateResolver->resolveForOpportunity($opportunity, $invoiceDate);
        $taxContext = TaxResolutionContext::fromOpportunity($opportunity);

        $summary = $this->taxResolutionContextHolder->using(
            $taxContext,
            fn (): DocumentTaxSummary => $this->documentTaxBreakdown->summarize(new DocumentTaxRequest(
                lines: $taxLines,
                currencyCode: $currency,
                organisationTaxClassId: $opportunity->member?->sale_tax_class_id,
                effectiveAt: $taxPointDate,
                pricesIncludeTax: false,
            )),
        );

        $this->assertPennyReconciliation($summary);

        $lineTaxByKey = $this->resolveLineTaxByKey($summary, $displayLines);

        $linesWithTax = array_map(
            function (FrozenInvoiceLineData $line) use ($lineTaxByKey): FrozenInvoiceLineData {
                return new FrozenInvoiceLineData(
                    invoice_item_id: $line->invoice_item_id,
                    line_key: $line->line_key,
                    source_type: $line->source_type,
                    description: $line->description,
                    quantity: $line->quantity,
                    unit_price: $line->unit_price,
                    discount: $line->discount,
                    net_total: $line->net_total,
                    tax: $lineTaxByKey[$line->line_key] ?? 0,
                    product_tax_class_id: $line->product_tax_class_id,
                    tax_treatment: $line->tax_treatment,
                    opportunity_item_id: $line->opportunity_item_id,
                    opportunity_cost_id: $line->opportunity_cost_id,
                    product_id: $line->product_id,
                    surcharge_id: $line->surcharge_id,
                    section_item_id: $line->section_item_id,
                    sort_order: $line->sort_order,
                );
            },
            $displayLines,
        );

        $taxRows = array_map(
            fn (DocumentTaxGroup $group): FrozenInvoiceTaxRowData => new FrozenInvoiceTaxRowData(
                invoice_tax_id: 0,
                product_tax_class_key: $group->productTaxClassKey,
                taxable_amount: $group->netTotal,
                tax_amount: $group->taxTotal,
                rate_percentage: $group->ratePercentage,
                tax_rate_name: $group->taxRateName,
                tax_rate_id: $group->taxRateId,
                tax_rule_id: $group->taxRuleId,
                result_type: ($group->resultType ?? TaxResultType::Standard)->value,
                legal_text: $group->legalText,
                zone_tax_rule_id: $group->zoneTaxRuleId,
            ),
            $summary->groups,
        );

        $this->applyTaxTreatmentsFromGroups($linesWithTax, $summary->groups);

        return [
            'lines' => $linesWithTax,
            'tax_rows' => $taxRows,
            'subtotal' => $netHeadline,
            'tax_total' => $summary->totalTax,
            'total' => $netHeadline + $summary->totalTax,
        ];
    }

    private function assertPennyReconciliation(DocumentTaxSummary $summary): void
    {
        $lineTaxSum = array_sum(array_map(
            fn (DocumentTaxLineAllocation $allocation): int => $allocation->taxMinor,
            $summary->lineAllocations,
        ));

        $groupTaxSum = array_sum(array_map(
            fn (DocumentTaxGroup $group): int => $group->taxTotal,
            $summary->groups,
        ));

        if ($lineTaxSum !== $groupTaxSum) {
            throw new LogicException(sprintf(
                'Tax penny reconciliation failed: line tax sum (%d) !== group tax sum (%d).',
                $lineTaxSum,
                $groupTaxSum,
            ));
        }
    }

    /**
     * @param  list<FrozenInvoiceLineData>  $displayLines
     * @return array<string, int>
     */
    private function resolveLineTaxByKey(DocumentTaxSummary $summary, array $displayLines): array
    {
        $allocations = $summary->lineAllocationsByKey();

        if (isset($allocations['deal-headline'])) {
            return $this->distributeDealHeadlineTax(
                $allocations['deal-headline']->taxMinor,
                $displayLines,
            );
        }

        $mapped = [];

        foreach ($displayLines as $line) {
            $mapped[$line->line_key] = $allocations[$line->line_key]->taxMinor ?? 0;
        }

        return $mapped;
    }

    /**
     * Largest-remainder distribution of deal-headline tax across display lines.
     *
     * @param  list<FrozenInvoiceLineData>  $displayLines
     * @return array<string, int>
     */
    private function distributeDealHeadlineTax(int $groupTax, array $displayLines): array
    {
        if ($displayLines === []) {
            return [];
        }

        if ($groupTax === 0) {
            return array_fill_keys(
                array_map(fn (FrozenInvoiceLineData $line): string => $line->line_key, $displayLines),
                0,
            );
        }

        $weights = [];

        foreach ($displayLines as $line) {
            $weights[$line->line_key] = $line->net_total;
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight === 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $allocations = [];
        $remainders = [];

        foreach ($weights as $lineKey => $weight) {
            $exactShare = bcdiv(
                bcmul((string) $groupTax, (string) $weight, 10),
                (string) $totalWeight,
                10,
            );

            $floorShare = (int) bcadd($exactShare, '0', 0);
            $allocations[$lineKey] = $floorShare;
            $remainders[$lineKey] = bcsub($exactShare, (string) $floorShare, 10);
        }

        $assigned = array_sum($allocations);
        $penniesRemaining = $groupTax - $assigned;

        if ($penniesRemaining !== 0) {
            $lineKeys = array_keys($weights);

            usort($lineKeys, function (string $left, string $right) use ($remainders, $penniesRemaining): int {
                $remainderComparison = bccomp($remainders[$right], $remainders[$left], 10);

                if ($remainderComparison !== 0) {
                    return $remainderComparison;
                }

                return $penniesRemaining > 0 ? strcmp($left, $right) : strcmp($right, $left);
            });

            $step = $penniesRemaining > 0 ? 1 : -1;
            $remaining = abs($penniesRemaining);

            for ($index = 0; $index < $remaining; $index++) {
                $lineKey = $lineKeys[$index % count($lineKeys)];
                $allocations[$lineKey] += $step;
            }
        }

        return $allocations;
    }

    /**
     * @param  list<FrozenInvoiceLineData>  $lines
     * @param  list<DocumentTaxGroup>  $groups
     */
    private function applyTaxTreatmentsFromGroups(array &$lines, array $groups): void
    {
        $treatmentByClass = [];

        foreach ($groups as $group) {
            $treatmentByClass[$group->productTaxClassKey] = ($group->resultType ?? TaxResultType::Standard)->value;
        }

        foreach ($lines as $index => $line) {
            $classKey = $line->product_tax_class_id === 0 ? 0 : $line->product_tax_class_id;
            $treatment = $treatmentByClass[$classKey] ?? TaxResultType::Standard->value;

            $lines[$index] = new FrozenInvoiceLineData(
                invoice_item_id: $line->invoice_item_id,
                line_key: $line->line_key,
                source_type: $line->source_type,
                description: $line->description,
                quantity: $line->quantity,
                unit_price: $line->unit_price,
                discount: $line->discount,
                net_total: $line->net_total,
                tax: $line->tax,
                product_tax_class_id: $line->product_tax_class_id,
                tax_treatment: $treatment,
                opportunity_item_id: $line->opportunity_item_id,
                opportunity_cost_id: $line->opportunity_cost_id,
                product_id: $line->product_id,
                surcharge_id: $line->surcharge_id,
                section_item_id: $line->section_item_id,
                sort_order: $line->sort_order,
            );
        }
    }

    private function currencyFor(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        return is_string($code) && $code !== '' ? $code : 'GBP';
    }
}

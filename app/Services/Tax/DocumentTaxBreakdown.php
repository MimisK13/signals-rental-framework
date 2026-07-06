<?php

namespace App\Services\Tax;

use App\Contracts\Tax\TaxResolver;
use App\Data\Tax\DocumentTaxLineInput;
use App\Data\Tax\DocumentTaxRequest;
use App\ValueObjects\Tax\DocumentTaxGroup;
use App\ValueObjects\Tax\DocumentTaxLineAllocation;
use App\ValueObjects\Tax\DocumentTaxSummary;

/**
 * Computes grouped document tax totals and reconciles per-line allocations using
 * largest-remainder (Hamilton) apportionment so Σ line tax ≡ Σ group tax exactly.
 *
 * Grouping follows the opportunity rollup convention: lines are bucketed by
 * product tax class id (`0` when no product class is supplied), each group's net
 * is taxed once at the minor-unit boundary, then the group tax is distributed
 * across member lines without lost or gained pennies.
 */
class DocumentTaxBreakdown
{
    public function __construct(
        private readonly TaxResolver $taxResolver,
    ) {}

    public function summarize(DocumentTaxRequest $request): DocumentTaxSummary
    {
        $normalisedLines = $this->normaliseLines($request);

        /** @var array<string, list<array{line: DocumentTaxLineInput, netMinor: int}>> $groupedLines */
        $groupedLines = [];

        foreach ($normalisedLines as $line) {
            $groupKey = $this->groupKey($line->productTaxClassId, $line->productTaxCategoryId);
            $groupedLines[$groupKey] ??= [];
            $groupedLines[$groupKey][] = [
                'line' => $line,
                'netMinor' => $line->netMinor,
            ];
        }

        $groups = [];
        $lineAllocations = [];
        $totalNet = 0;
        $totalTax = 0;

        foreach ($groupedLines as $groupKey => $members) {
            [$productTaxClassKey, $productTaxCategoryKey] = $this->decodeGroupKey($groupKey);
            $productTaxClassId = $productTaxClassKey === 0 ? null : $productTaxClassKey;
            $productTaxCategoryId = $productTaxCategoryKey === 0 ? null : $productTaxCategoryKey;

            $groupNet = array_sum(array_column($members, 'netMinor'));

            $groupTaxResult = $this->taxResolver->calculateExclusive(
                $groupNet,
                $request->currencyCode,
                $request->organisationTaxClassId,
                $productTaxClassId,
                $request->effectiveAt,
                $productTaxCategoryId,
            );

            $groupTax = $groupTaxResult->taxAmount;
            $allocated = $this->distributeLargestRemainder($members, $groupTax);

            foreach ($members as $member) {
                $lineKey = $member['line']->lineKey;
                $lineNet = $member['netMinor'];
                $lineTax = $allocated[$lineKey] ?? 0;

                $lineAllocations[] = new DocumentTaxLineAllocation(
                    lineKey: $lineKey,
                    netMinor: $lineNet,
                    taxMinor: $lineTax,
                    productTaxClassKey: $productTaxClassKey,
                );
            }

            $groups[] = new DocumentTaxGroup(
                productTaxClassKey: $productTaxClassKey,
                netTotal: $groupNet,
                taxTotal: $groupTax,
                ratePercentage: $groupTaxResult->ratePercentage,
                taxRateName: $groupTaxResult->taxRateName,
                taxRateId: $groupTaxResult->taxRateId,
                taxRuleId: $groupTaxResult->taxRuleId,
                resultType: $groupTaxResult->resultType,
                legalText: $groupTaxResult->legalText,
                zoneTaxRuleId: $groupTaxResult->zoneTaxRuleId,
            );

            $totalNet += $groupNet;
            $totalTax += $groupTax;
        }

        return new DocumentTaxSummary(
            groups: $groups,
            lineAllocations: $lineAllocations,
            totalNet: $totalNet,
            totalTax: $totalTax,
        );
    }

    /**
     * @return list<DocumentTaxLineInput>
     */
    private function normaliseLines(DocumentTaxRequest $request): array
    {
        if (! $request->pricesIncludeTax) {
            return $request->lines;
        }

        return array_map(function (DocumentTaxLineInput $line) use ($request): DocumentTaxLineInput {
            $netResult = $this->taxResolver->calculateInclusive(
                $line->netMinor,
                $request->currencyCode,
                $request->organisationTaxClassId,
                $line->productTaxClassId,
                $request->effectiveAt,
                $line->productTaxCategoryId,
            );

            return new DocumentTaxLineInput(
                lineKey: $line->lineKey,
                netMinor: $netResult->netAmount,
                productTaxClassId: $line->productTaxClassId,
                productTaxCategoryId: $line->productTaxCategoryId,
                quantity: $line->quantity,
                taxTreatment: $line->taxTreatment,
            );
        }, $request->lines);
    }

    /**
     * Largest-remainder (Hamilton) apportionment of a fixed group tax across lines.
     *
     * @param  list<array{line: DocumentTaxLineInput, netMinor: int}>  $members
     * @return array<string, int>
     */
    private function distributeLargestRemainder(array $members, int $groupTax): array
    {
        if ($members === []) {
            return [];
        }

        if ($groupTax === 0) {
            return array_fill_keys(
                array_map(fn (array $member): string => $member['line']->lineKey, $members),
                0,
            );
        }

        $totalWeight = array_sum(array_column($members, 'netMinor'));

        if ($totalWeight === 0) {
            return array_fill_keys(
                array_map(fn (array $member): string => $member['line']->lineKey, $members),
                0,
            );
        }

        $allocations = [];
        $remainders = [];

        foreach ($members as $member) {
            $lineKey = $member['line']->lineKey;
            $weight = $member['netMinor'];

            $exactShare = bcdiv(
                bcmul((string) $groupTax, (string) $weight, 10),
                (string) $totalWeight,
                10,
            );

            $floorShare = $exactShare[0] === '-'
                ? (int) bcsub($exactShare, '0.9999999999', 0)
                : (int) bcadd($exactShare, '0', 0);

            $allocations[$lineKey] = $floorShare;
            $remainders[$lineKey] = bcsub($exactShare, (string) $floorShare, 10);
        }

        $assigned = array_sum($allocations);
        $penniesRemaining = $groupTax - $assigned;

        if ($penniesRemaining === 0) {
            return $allocations;
        }

        $lineKeys = array_map(fn (array $member): string => $member['line']->lineKey, $members);

        usort($lineKeys, function (string $left, string $right) use ($remainders, $penniesRemaining): int {
            $remainderComparison = bccomp($remainders[$right], $remainders[$left], 10);

            if ($remainderComparison !== 0) {
                return $remainderComparison;
            }

            if ($penniesRemaining > 0) {
                return strcmp($left, $right);
            }

            return strcmp($right, $left);
        });

        $step = $penniesRemaining > 0 ? 1 : -1;
        $remaining = abs($penniesRemaining);

        for ($index = 0; $index < $remaining; $index++) {
            $lineKey = $lineKeys[$index % count($lineKeys)];
            $allocations[$lineKey] += $step;
        }

        return $allocations;
    }

    private function groupKey(?int $productTaxClassId, ?int $productTaxCategoryId): string
    {
        return ($productTaxClassId ?? 0).':'.($productTaxCategoryId ?? 0);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function decodeGroupKey(string $groupKey): array
    {
        [$classKey, $categoryKey] = explode(':', $groupKey, 2);

        return [(int) $classKey, (int) $categoryKey];
    }
}

<?php

namespace App\Services\Tax;

use App\Contracts\Tax\TaxResolver;
use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\TaxCategoryType;
use App\Enums\TaxResultType;
use App\Models\TaxCategory;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Services\TaxCalculator;
use App\ValueObjects\TaxResult;
use DateTimeInterface;

/**
 * Zone-aware tax resolver: matches cross-border zone rules first, then falls back
 * to the org × product class matrix via {@see TaxCalculator}.
 *
 * When a zone rule resolves to a taxable result type but the class matrix cannot
 * refine a rate (typically because tax classes were not assigned), the resolver
 * falls back to the seeded Standard / Reduced / Zero {@see TaxRate} rows.
 */
class ZoneAwareTaxResolver implements TaxResolver
{
    public function __construct(
        private readonly TaxCalculator $matrixCalculator,
        private readonly TaxZoneRuleResolver $zoneRuleResolver,
        private readonly TaxResolutionContextHolder $contextHolder,
        private readonly TaxRateFallbackResolver $rateFallbackResolver,
    ) {}

    public function calculateExclusive(
        int $netMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
        ?int $productTaxCategoryId = null,
    ): TaxResult {
        $context = $this->contextHolder->get();

        if ($context === null || ! $context->canMatchZoneRules()) {
            return $this->matrixCalculator->calculateExclusive(
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            );
        }

        if ($context->taxExempt) {
            return $this->zeroTaxResult(
                $netMinorUnits,
                $currencyCode,
                TaxResultType::Exempt,
                taxRateName: 'Exempt',
                legalText: null,
            );
        }

        $zoneRule = $this->zoneRuleResolver->resolve(new TaxZoneRuleMatchRequest(
            seller_zone_id: $context->sellerZoneId,
            buyer_zone_id: $context->buyerZoneId,
            buyer_type: $context->buyerType,
            has_tax_id: $context->hasTaxId,
            tax_category_id: $productTaxCategoryId,
        ));

        if ($zoneRule === null) {
            return $this->matrixCalculator->calculateExclusive(
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            );
        }

        return $this->applyZoneRule(
            $zoneRule,
            $netMinorUnits,
            $currencyCode,
            $organisationTaxClassId,
            $productTaxClassId,
            $effectiveAt,
            $productTaxCategoryId,
            grossMinorUnits: null,
        );
    }

    public function calculateInclusive(
        int $grossMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
        ?int $productTaxCategoryId = null,
    ): TaxResult {
        $context = $this->contextHolder->get();

        if ($context === null || ! $context->canMatchZoneRules()) {
            return $this->matrixCalculator->calculateInclusive(
                $grossMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            );
        }

        if ($context->taxExempt) {
            return $this->zeroTaxResult(
                $grossMinorUnits,
                $currencyCode,
                TaxResultType::Exempt,
                taxRateName: 'Exempt',
                legalText: null,
                isInclusiveInput: true,
            );
        }

        $zoneRule = $this->zoneRuleResolver->resolve(new TaxZoneRuleMatchRequest(
            seller_zone_id: $context->sellerZoneId,
            buyer_zone_id: $context->buyerZoneId,
            buyer_type: $context->buyerType,
            has_tax_id: $context->hasTaxId,
            tax_category_id: $productTaxCategoryId,
        ));

        if ($zoneRule === null) {
            return $this->matrixCalculator->calculateInclusive(
                $grossMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            );
        }

        if ($this->zoneRuleResultType($zoneRule) === TaxResultType::Standard) {
            return $this->applyStandardZoneRule(
                $zoneRule,
                netMinorUnits: $grossMinorUnits,
                currencyCode: $currencyCode,
                organisationTaxClassId: $organisationTaxClassId,
                productTaxClassId: $productTaxClassId,
                effectiveAt: $effectiveAt,
                productTaxCategoryId: $productTaxCategoryId,
                grossMinorUnits: $grossMinorUnits,
                isInclusiveInput: true,
            );
        }

        return $this->applyZoneRule(
            $zoneRule,
            netMinorUnits: $grossMinorUnits,
            currencyCode: $currencyCode,
            organisationTaxClassId: $organisationTaxClassId,
            productTaxClassId: $productTaxClassId,
            effectiveAt: $effectiveAt,
            productTaxCategoryId: $productTaxCategoryId,
            grossMinorUnits: $grossMinorUnits,
            isInclusiveInput: true,
        );
    }

    private function applyZoneRule(
        TaxRule $zoneRule,
        int $netMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId,
        ?int $productTaxClassId,
        ?DateTimeInterface $effectiveAt,
        ?int $productTaxCategoryId,
        ?int $grossMinorUnits,
        bool $isInclusiveInput = false,
    ): TaxResult {
        $resultType = $this->zoneRuleResultType($zoneRule);

        return match ($resultType) {
            TaxResultType::Standard => $this->applyStandardZoneRule(
                $zoneRule,
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
                $grossMinorUnits,
                $isInclusiveInput,
            ),
            // Compound-tax display is N/A while compound tax remains deferred per the Phase-4 binding decision.
            TaxResultType::ReverseCharge => $this->zeroTaxResult(
                $isInclusiveInput ? $grossMinorUnits : $netMinorUnits,
                $currencyCode,
                TaxResultType::ReverseCharge,
                taxRateName: $this->reverseChargeLabel(),
                legalText: $zoneRule->legal_text,
                zoneTaxRuleId: $zoneRule->id,
                isInclusiveInput: $isInclusiveInput,
            ),
            TaxResultType::ZeroRated => $this->zeroTaxResult(
                $isInclusiveInput ? $grossMinorUnits : $netMinorUnits,
                $currencyCode,
                TaxResultType::ZeroRated,
                taxRateName: 'Zero rated',
                legalText: $zoneRule->legal_text,
                zoneTaxRuleId: $zoneRule->id,
                isInclusiveInput: $isInclusiveInput,
            ),
            TaxResultType::Exempt => $this->zeroTaxResult(
                $isInclusiveInput ? $grossMinorUnits : $netMinorUnits,
                $currencyCode,
                TaxResultType::Exempt,
                taxRateName: 'Exempt',
                legalText: $zoneRule->legal_text,
                zoneTaxRuleId: $zoneRule->id,
                isInclusiveInput: $isInclusiveInput,
            ),
            TaxResultType::OutsideScope => $this->zeroTaxResult(
                $isInclusiveInput ? $grossMinorUnits : $netMinorUnits,
                $currencyCode,
                TaxResultType::OutsideScope,
                taxRateName: 'Outside scope',
                legalText: $zoneRule->legal_text,
                zoneTaxRuleId: $zoneRule->id,
                isInclusiveInput: $isInclusiveInput,
            ),
        };
    }

    private function applyStandardZoneRule(
        TaxRule $zoneRule,
        int $netMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId,
        ?int $productTaxClassId,
        ?DateTimeInterface $effectiveAt,
        ?int $productTaxCategoryId,
        ?int $grossMinorUnits,
        bool $isInclusiveInput = false,
    ): TaxResult {
        $categoryRate = $this->resolveCategorySteeredRate($productTaxCategoryId, $zoneRule);

        if ($categoryRate !== null) {
            $categoryResult = $isInclusiveInput
                ? $this->matrixCalculator->calculateInclusiveWithTaxRate(
                    $grossMinorUnits ?? $netMinorUnits,
                    $currencyCode,
                    $categoryRate,
                )
                : $this->matrixCalculator->calculateExclusiveWithTaxRate(
                    $netMinorUnits,
                    $currencyCode,
                    $categoryRate,
                );

            return $this->mergeZoneMetadata($categoryResult, $zoneRule);
        }

        $matrixResult = $isInclusiveInput
            ? $this->matrixCalculator->calculateInclusive(
                $grossMinorUnits ?? $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            )
            : $this->matrixCalculator->calculateExclusive(
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
                $productTaxCategoryId,
            );

        if ($matrixResult->taxRateId !== null) {
            return $this->mergeZoneMetadata($matrixResult, $zoneRule);
        }

        $fallbackRate = $this->rateFallbackResolver->resolve(
            $zoneRule,
            TaxResultType::Standard,
            $productTaxCategoryId,
        );

        if ($fallbackRate === null) {
            return $this->mergeZoneMetadata($matrixResult, $zoneRule);
        }

        $fallbackResult = $isInclusiveInput
            ? $this->matrixCalculator->calculateInclusiveWithTaxRate(
                $grossMinorUnits ?? $netMinorUnits,
                $currencyCode,
                $fallbackRate,
            )
            : $this->matrixCalculator->calculateExclusiveWithTaxRate(
                $netMinorUnits,
                $currencyCode,
                $fallbackRate,
            );

        return $this->mergeZoneMetadata($fallbackResult, $zoneRule);
    }

    private function resolveCategorySteeredRate(?int $productTaxCategoryId, TaxRule $zoneRule): ?TaxRate
    {
        if ($productTaxCategoryId === null) {
            return null;
        }

        $category = TaxCategory::query()->find($productTaxCategoryId);

        if ($category === null) {
            return null;
        }

        $type = $category->getAttribute('type');
        $categoryType = $type instanceof TaxCategoryType
            ? $type
            : TaxCategoryType::tryFrom((string) $type);

        if ($categoryType === null || $categoryType === TaxCategoryType::Standard) {
            return null;
        }

        return $this->rateFallbackResolver->resolve(
            $zoneRule,
            TaxResultType::Standard,
            $productTaxCategoryId,
        );
    }

    private function mergeZoneMetadata(TaxResult $matrixResult, TaxRule $zoneRule): TaxResult
    {
        return new TaxResult(
            taxRateName: $matrixResult->taxRateName,
            ratePercentage: $matrixResult->ratePercentage,
            netAmount: $matrixResult->netAmount,
            taxAmount: $matrixResult->taxAmount,
            grossAmount: $matrixResult->grossAmount,
            currencyCode: $matrixResult->currencyCode,
            taxRateId: $matrixResult->taxRateId,
            taxRuleId: $matrixResult->taxRuleId,
            resultType: TaxResultType::Standard,
            legalText: $zoneRule->legal_text,
            zoneTaxRuleId: $zoneRule->id,
        );
    }

    private function zeroTaxResult(
        int $amountMinorUnits,
        string $currencyCode,
        TaxResultType $resultType,
        string $taxRateName,
        ?string $legalText,
        ?int $zoneTaxRuleId = null,
        bool $isInclusiveInput = false,
    ): TaxResult {
        if ($isInclusiveInput) {
            return new TaxResult(
                taxRateName: $taxRateName,
                ratePercentage: '0.0000',
                netAmount: $amountMinorUnits,
                taxAmount: 0,
                grossAmount: $amountMinorUnits,
                currencyCode: $currencyCode,
                zoneTaxRuleId: $zoneTaxRuleId,
                resultType: $resultType,
                legalText: $legalText,
            );
        }

        return new TaxResult(
            taxRateName: $taxRateName,
            ratePercentage: '0.0000',
            netAmount: $amountMinorUnits,
            taxAmount: 0,
            grossAmount: $amountMinorUnits,
            currencyCode: $currencyCode,
            zoneTaxRuleId: $zoneTaxRuleId,
            resultType: $resultType,
            legalText: $legalText,
        );
    }

    private function zoneRuleResultType(TaxRule $zoneRule): TaxResultType
    {
        return $zoneRule->result_type ?? TaxResultType::Standard;
    }

    private function reverseChargeLabel(): string
    {
        return (string) settings('finance.reverse_charge_label');
    }
}

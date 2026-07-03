<?php

namespace App\Services\Tax;

use App\Contracts\Tax\TaxResolver;
use App\Data\Tax\TaxZoneRuleMatchRequest;
use App\Enums\TaxResultType;
use App\Models\TaxRule;
use App\Services\TaxCalculator;
use App\ValueObjects\TaxResult;
use DateTimeInterface;

/**
 * Zone-aware tax resolver: matches cross-border zone rules first, then falls back
 * to the org × product class matrix via {@see TaxCalculator}.
 */
class ZoneAwareTaxResolver implements TaxResolver
{
    public function __construct(
        private readonly TaxCalculator $matrixCalculator,
        private readonly TaxZoneRuleResolver $zoneRuleResolver,
        private readonly TaxResolutionContextHolder $contextHolder,
    ) {}

    public function calculateExclusive(
        int $netMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
    ): TaxResult {
        $context = $this->contextHolder->get();

        if ($context === null || ! $context->canMatchZoneRules()) {
            return $this->matrixCalculator->calculateExclusive(
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
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
        ));

        if ($zoneRule === null) {
            return $this->matrixCalculator->calculateExclusive(
                $netMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
            );
        }

        return $this->applyZoneRule(
            $zoneRule,
            $netMinorUnits,
            $currencyCode,
            $organisationTaxClassId,
            $productTaxClassId,
            $effectiveAt,
            grossMinorUnits: null,
        );
    }

    public function calculateInclusive(
        int $grossMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
    ): TaxResult {
        $context = $this->contextHolder->get();

        if ($context === null || ! $context->canMatchZoneRules()) {
            return $this->matrixCalculator->calculateInclusive(
                $grossMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
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
        ));

        if ($zoneRule === null) {
            return $this->matrixCalculator->calculateInclusive(
                $grossMinorUnits,
                $currencyCode,
                $organisationTaxClassId,
                $productTaxClassId,
                $effectiveAt,
            );
        }

        if ($zoneRule->result_type === TaxResultType::Standard) {
            return $this->mergeZoneMetadata(
                $this->matrixCalculator->calculateInclusive(
                    $grossMinorUnits,
                    $currencyCode,
                    $organisationTaxClassId,
                    $productTaxClassId,
                    $effectiveAt,
                ),
                $zoneRule,
            );
        }

        return $this->applyZoneRule(
            $zoneRule,
            netMinorUnits: $grossMinorUnits,
            currencyCode: $currencyCode,
            organisationTaxClassId: $organisationTaxClassId,
            productTaxClassId: $productTaxClassId,
            effectiveAt: $effectiveAt,
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
        ?int $grossMinorUnits,
        bool $isInclusiveInput = false,
    ): TaxResult {
        $resultType = $zoneRule->result_type ?? TaxResultType::Standard;

        return match ($resultType) {
            TaxResultType::Standard => $this->mergeZoneMetadata(
                $this->matrixCalculator->calculateExclusive(
                    $netMinorUnits,
                    $currencyCode,
                    $organisationTaxClassId,
                    $productTaxClassId,
                    $effectiveAt,
                ),
                $zoneRule,
            ),
            TaxResultType::ReverseCharge => $this->zeroTaxResult(
                $isInclusiveInput ? $grossMinorUnits : $netMinorUnits,
                $currencyCode,
                TaxResultType::ReverseCharge,
                taxRateName: 'Reverse charge',
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
}

<?php

namespace App\Contracts\Tax;

use App\Services\Tax\DocumentTaxBreakdown;
use App\Services\TaxCalculator;
use App\ValueObjects\TaxResult;
use DateTimeInterface;

/**
 * Resolves applicable tax rules and performs exclusive/inclusive calculations.
 *
 * Document tax code MUST resolve rates through this contract rather than calling
 * {@see TaxCalculator} directly. The open-source default uses the
 * org × product tax-class matrix; zone-aware resolution (P13) can rebind this
 * contract without touching {@see DocumentTaxBreakdown}.
 */
interface TaxResolver
{
    /**
     * Calculate tax for a NET amount in minor units (exclusive / tax-additive mode).
     */
    public function calculateExclusive(
        int $netMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
        ?int $productTaxCategoryId = null,
    ): TaxResult;

    /**
     * Extract embedded tax from a GROSS amount in minor units (inclusive mode).
     */
    public function calculateInclusive(
        int $grossMinorUnits,
        string $currencyCode,
        ?int $organisationTaxClassId = null,
        ?int $productTaxClassId = null,
        ?DateTimeInterface $effectiveAt = null,
        ?int $productTaxCategoryId = null,
    ): TaxResult;
}

<?php

declare(strict_types=1);

namespace App\Domain\Tax\ValueObjects;

use App\Domain\Tax\Enums\TaxResultType;

/**
 * One product-tax-class group within a document tax breakdown.
 */
final class DocumentTaxGroup
{
    public function __construct(
        public readonly int $productTaxClassKey,
        public readonly int $netTotal,
        public readonly int $taxTotal,
        public readonly string $ratePercentage,
        public readonly string $taxRateName,
        public readonly ?int $taxRateId = null,
        public readonly ?int $taxRuleId = null,
        public readonly ?TaxResultType $resultType = null,
        public readonly ?string $legalText = null,
        public readonly ?int $zoneTaxRuleId = null,
    ) {}
}

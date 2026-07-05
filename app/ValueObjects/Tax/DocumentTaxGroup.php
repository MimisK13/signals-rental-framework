<?php

namespace App\ValueObjects\Tax;

use App\Enums\TaxResultType;

/**
 * One product-tax-class group within a document tax breakdown.
 */
class DocumentTaxGroup
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

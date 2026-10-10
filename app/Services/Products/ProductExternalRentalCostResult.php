<?php

namespace App\Services\Products;

final readonly class ProductExternalRentalCostResult
{
    /**
     * @param  list<string>  $unavailableReasons
     */
    public function __construct(
        public int $productId,
        public ?int $expectedCost,
        public ?int $actualCost,
        public ?int $variance,
        public ?string $currencyCode,
        public bool $actualCostComplete,
        public bool $financialsComplete,
        public array $unavailableReasons = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Domain\Products\Services;

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

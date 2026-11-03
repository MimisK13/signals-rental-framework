<?php

declare(strict_types=1);

namespace App\Domain\Products\Investment\Services;

final readonly class WindowedProductRevenueResult
{
    /** @param list<string> $reasons */
    public function __construct(
        public int $amount,
        public ?string $currencyCode,
        public bool $complete,
        public array $reasons = [],
    ) {}
}

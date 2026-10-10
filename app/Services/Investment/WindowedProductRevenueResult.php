<?php

namespace App\Services\Investment;

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

<?php

namespace App\Services\Pricing;

use App\Contracts\Pricing\DiscountSource;

class DiscountSourceRegistry
{
    /** @var array<string, DiscountSource> */
    private array $sources = [];

    public function register(DiscountSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * @return array<string, DiscountSource>
     */
    public function all(): array
    {
        return $this->sources;
    }
}

<?php

namespace App\Services\Pricing;

use App\Contracts\Pricing\DealPriceValidator;

class DealPriceValidatorRegistry
{
    /** @var array<string, DealPriceValidator> */
    private array $validators = [];

    public function register(DealPriceValidator $validator): void
    {
        $this->validators[$validator->key()] = $validator;
    }

    /**
     * @return array<string, DealPriceValidator>
     */
    public function all(): array
    {
        return $this->validators;
    }
}

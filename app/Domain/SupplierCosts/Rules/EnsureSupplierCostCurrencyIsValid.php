<?php

declare(strict_types=1);

namespace App\Domain\SupplierCosts\Rules;

use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Illuminate\Validation\ValidationException;

final class EnsureSupplierCostCurrencyIsValid
{
    public function __invoke(string $currencyCode): string
    {
        $currencyCode = strtoupper($currencyCode);

        try {
            Currency::of($currencyCode);
        } catch (UnknownCurrencyException) {
            throw ValidationException::withMessages([
                'currency_code' => 'Choose a supported ISO currency code.',
            ]);
        }

        return $currencyCode;
    }
}

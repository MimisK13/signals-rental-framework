<?php

declare(strict_types=1);

namespace App\Domain\Rates\Rules;

use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Illuminate\Validation\ValidationException;

final class EnsureProductRateCurrencyIsValid
{
    public function __invoke(string $currency): string
    {
        $currency = strtoupper($currency);

        try {
            Currency::of($currency);
        } catch (UnknownCurrencyException) {
            throw ValidationException::withMessages([
                'currency' => 'Choose a supported ISO currency code.',
            ]);
        }

        return $currency;
    }
}

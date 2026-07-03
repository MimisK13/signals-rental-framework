<?php

namespace App\Services\Pricing;

use App\Enums\PricingRoundingDirection;
use App\Models\Currency;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;

/**
 * Single rounding seam for Phase 4 money calculations.
 *
 * Resolves the tenant rounding direction from {@see settings('pricing.rounding_direction')}
 * on every call so fire-time settings are honoured without constructor caching.
 */
class RoundingStrategy
{
    /**
     * Round a decimal amount expressed in minor units to a whole minor-unit integer.
     */
    public function roundMinorAmount(string $decimalMinorAmount, string $currencyCode): int
    {
        return BigDecimal::of($decimalMinorAmount)
            ->toScale(0, $this->resolveRoundingMode())
            ->toInt();
    }

    /**
     * Convert a major-unit decimal amount to minor units using the currency's scale.
     */
    public function roundMajorToMinorUnits(string $majorAmount, string $currencyCode): int
    {
        return Money::of($majorAmount, $currencyCode, null, $this->resolveRoundingMode())
            ->getMinorAmount()
            ->toInt();
    }

    /**
     * Resolve ISO 4217 minor-unit scale (JPY 0, GBP 2, KWD 3, …).
     */
    public function fractionDigits(string $currencyCode): int
    {
        $currency = Currency::query()->where('code', $currencyCode)->first();

        if ($currency !== null) {
            return (int) $currency->decimal_places;
        }

        return BrickCurrency::of($currencyCode)->getDefaultFractionDigits();
    }

    private function resolveRoundingMode(): RoundingMode
    {
        $direction = settings('pricing.rounding_direction', PricingRoundingDirection::HalfUp->value);

        return match (PricingRoundingDirection::tryFrom((string) $direction) ?? PricingRoundingDirection::HalfUp) {
            PricingRoundingDirection::Bankers => RoundingMode::HALF_EVEN,
            PricingRoundingDirection::HalfUp => RoundingMode::HALF_UP,
        };
    }
}

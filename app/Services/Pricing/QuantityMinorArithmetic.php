<?php

namespace App\Services\Pricing;

use App\ValueObjects\CalculationContext;

/**
 * Decimal-quantity × minor-unit arithmetic with tenant rounding at the boundary.
 *
 * Opportunity line quantities are decimal strings (decimal(10,2)). Use this helper
 * for any path that derives money or displayed amounts from quantity — never
 * `(int) round((float) $quantity)`.
 */
class QuantityMinorArithmetic
{
    public function __construct(
        private readonly RoundingStrategy $roundingStrategy,
    ) {}

    /**
     * @param  numeric-string  $quantity
     */
    public function multiplyMinorByQuantity(int $minorPerUnit, string $quantity, string $currencyCode): int
    {
        $normalized = $this->normalizeQuantity($quantity);

        if (bccomp($normalized, '0', 2) <= 0) {
            return 0;
        }

        $raw = bcmul((string) $minorPerUnit, $normalized, 6);

        return $this->roundingStrategy->roundMinorAmount($raw, $currencyCode);
    }

    /**
     * @param  numeric-string  $quantity
     */
    public function normalizeQuantity(string $quantity): string
    {
        if (bccomp($quantity, '0', 2) <= 0) {
            return '0.00';
        }

        return bcadd($quantity, '0', 2);
    }

    /**
     * Whether the quantity has a fractional component (e.g. 1.50 has none, 1.5 does).
     *
     * @param  numeric-string  $quantity
     */
    public function quantityHasFraction(string $quantity): bool
    {
        return bccomp(bcmod($this->normalizeQuantity($quantity), '1', 2), '0', 2) !== 0;
    }

    /**
     * Whole-unit count for rate-engine {@see CalculationContext}
     * where strategies apply quantity factor tables on integer unit boundaries.
     *
     * Fractional quantities must use {@see multiplyMinorByQuantity} on the resolved
     * per-unit rate instead of passing a silently rounded float through the engine.
     *
     * @param  numeric-string  $quantity
     */
    public function wholeUnitCountForRateEngine(string $quantity): int
    {
        return max(0, (int) bcadd($this->normalizeQuantity($quantity), '0', 0));
    }
}

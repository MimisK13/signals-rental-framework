<?php

namespace App\Services\Pricing;

use App\Models\PriceCategoryTier;
use Brick\Money\Money;

/**
 * Applies a member's price-category tier multiplier to a resolved category rate
 * unit price (Stage 1 only). Fallback (null-category) rates are never multiplied.
 */
class PriceCategoryTierMultiplier
{
    public function __construct(
        private readonly RoundingStrategy $roundingStrategy,
    ) {}

    /**
     * @return array{unit_price: int, tier_id: ?int, tier_multiplier: ?string}
     */
    public function apply(
        int $unitPriceMinor,
        string $currency,
        ?int $memberPriceCategoryId,
        ?PriceCategoryTier $tier,
        ?int $resolvedRatePriceCategoryId,
    ): array {
        if (
            $memberPriceCategoryId === null
            || $resolvedRatePriceCategoryId !== $memberPriceCategoryId
            || $tier === null
        ) {
            return [
                'unit_price' => $unitPriceMinor,
                'tier_id' => null,
                'tier_multiplier' => null,
            ];
        }

        $multiplier = (string) $tier->multiplier;
        $rational = Money::ofMinor($unitPriceMinor, $currency)
            ->toRational()
            ->multipliedBy($multiplier);

        return [
            'unit_price' => $this->roundingStrategy->roundRationalToMinorUnits($rational, $currency),
            'tier_id' => $tier->id,
            'tier_multiplier' => $multiplier,
        ];
    }
}

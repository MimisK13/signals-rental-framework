<?php

declare(strict_types=1);

namespace App\Domain\Rates\Services\RateEngine\Strategies;

use App\Domain\Rates\Contracts\CalculationStrategy;
use App\Domain\Rates\Enums\BasePeriod;
use App\Domain\Rates\Enums\CalculationStrategyType;
use App\Domain\Rates\ValueObjects\CalculationContext;
use App\Domain\Rates\ValueObjects\RateBreakdown;
use App\Domain\Rates\ValueObjects\RateLineItem;
use App\Support\ConfigSchema\Schema;

/**
 * Charges a single flat amount regardless of how long the rental runs. Used for
 * consumables, damage waivers, delivery fees and similar non-time-dependent
 * charges. There is no base period and duration does not affect the result.
 *
 * Multipliers are meaningless for a flat charge (there are no per-period tiers),
 * so they are not supported; factors may still scale the flat amount by quantity
 * range in M2.
 */
final class FixedStrategy implements CalculationStrategy
{
    public function identifier(): string
    {
        return CalculationStrategyType::Fixed->value;
    }

    public function label(): string
    {
        return CalculationStrategyType::Fixed->label();
    }

    /**
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array
    {
        return CalculationStrategyType::Fixed->allowedBasePeriods();
    }

    public function supportsMultiplier(): bool
    {
        return false;
    }

    public function supportsFactor(): bool
    {
        return true;
    }

    public function configSchema(): Schema
    {
        return Schema::make();
    }

    public function calculate(CalculationContext $context): RateBreakdown
    {
        $lineItem = new RateLineItem(
            periodFrom: 1,
            periodTo: 1,
            label: sprintf('Fixed charge × %d', $context->unitPriceMinor),
            multiplier: '1.0',
            unitPriceMinor: $context->unitPriceMinor,
            lineTotalMinor: $context->unitPriceMinor,
        );

        return new RateBreakdown(
            unitPriceMinor: $context->unitPriceMinor,
            currency: $context->currency,
            units: 1,
            unitLabel: 'fixed',
            perUnitSubtotalMinor: $context->unitPriceMinor,
            quantity: $context->quantity,
            lineItems: [$lineItem],
            appliedModifiers: [],
        );
    }
}

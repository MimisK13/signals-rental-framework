<?php

declare(strict_types=1);

namespace App\Domain\Rates\Services\RateEngine\Strategies;

use App\Domain\Rates\Contracts\CalculationStrategy;
use App\Domain\Rates\Enums\BasePeriod;
use App\Domain\Rates\Enums\CalculationStrategyType;
use App\Domain\Rates\Services\RateEngine\Strategies\Concerns\CountsChargeableUnits;
use App\Domain\Rates\ValueObjects\CalculationContext;
use App\Domain\Rates\ValueObjects\RateBreakdown;
use App\Domain\Rates\ValueObjects\RateLineItem;
use App\Support\ConfigSchema\Schema;
use RuntimeException;

/**
 * Charges one unit for every base-period interval (hour, day, week, month) that
 * elapses during the rental window. This is the most common rental pricing
 * model and the basis for the daily/hourly/weekly/monthly presets.
 *
 * The breakdown returned here is the *base* charge: a flat per-unit price across
 * every unit. The multiplier modifier (M2) later splits the single line item
 * into tiers and the factor modifier scales the subtotal.
 */
final class PeriodStrategy implements CalculationStrategy
{
    use CountsChargeableUnits;

    public function identifier(): string
    {
        return CalculationStrategyType::Period->value;
    }

    public function label(): string
    {
        return CalculationStrategyType::Period->label();
    }

    /**
     * @return array<int, BasePeriod>
     */
    public function allowedBasePeriods(): array
    {
        return CalculationStrategyType::Period->allowedBasePeriods();
    }

    public function supportsMultiplier(): bool
    {
        return true;
    }

    public function supportsFactor(): bool
    {
        return true;
    }

    public function configSchema(): Schema
    {
        return Schema::make(...$this->timeOptionFields());
    }

    public function calculate(CalculationContext $context): RateBreakdown
    {
        $period = $context->basePeriod;

        if (! $period instanceof BasePeriod) {
            throw new RuntimeException('The period strategy requires a base period.');
        }

        $units = $this->chargeableUnits($context, $period);
        $unitLabel = $this->unitLabel($period);
        $perUnitSubtotalMinor = $units * $context->unitPriceMinor;

        $lineItem = new RateLineItem(
            periodFrom: 1,
            periodTo: $units,
            label: sprintf('%d %s × %d', $units, $unitLabel, $context->unitPriceMinor),
            multiplier: '1.0',
            unitPriceMinor: $context->unitPriceMinor,
            lineTotalMinor: $perUnitSubtotalMinor,
        );

        return new RateBreakdown(
            unitPriceMinor: $context->unitPriceMinor,
            currency: $context->currency,
            units: $units,
            unitLabel: $unitLabel,
            perUnitSubtotalMinor: $perUnitSubtotalMinor,
            quantity: $context->quantity,
            lineItems: [$lineItem],
            appliedModifiers: [],
        );
    }
}

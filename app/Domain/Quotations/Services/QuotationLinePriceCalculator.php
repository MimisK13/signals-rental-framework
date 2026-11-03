<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\ValueObjects\QuotationLinePricing;
use App\Domain\Rates\Enums\RateTransactionType;
use App\Domain\Rates\Models\ProductRate;
use App\Domain\Rates\Services\RateEngine\RateCalculator;
use App\Domain\Rates\Services\RateEngine\RateResolver;
use App\Domain\Rates\ValueObjects\CalculationContext;
use App\Domain\Tax\Contracts\TaxResolver;
use App\Enums\LineItemTransactionType;
use App\Services\Pricing\QuantityMinorArithmetic;
use App\Services\Pricing\RoundingStrategy;
use Illuminate\Support\Carbon;

final readonly class QuotationLinePriceCalculator
{
    public function __construct(
        private RateResolver $rateResolver,
        private RateCalculator $rateCalculator,
        private QuantityMinorArithmetic $quantityMinor,
        private RoundingStrategy $rounding,
        private TaxResolver $taxResolver,
    ) {}

    public function calculate(
        Quotation $quotation,
        QuotationItemType $itemType,
        ?Product $product,
        string $quantity,
        LineItemTransactionType $transactionType,
        ?int $manualUnitPrice,
        ?string $discountPercent,
        ?string $startsAt,
        ?string $endsAt,
    ): QuotationLinePricing {
        if (! $itemType->isPriceable()) {
            return new QuotationLinePricing(0, 0, 0, 0, null, null, ['source' => 'structural']);
        }

        $quantity = $this->quantityMinor->normalizeQuantity($quantity);
        $start = Carbon::parse($startsAt ?? $quotation->starts_at ?? now('UTC'));
        $end = Carbon::parse($endsAt ?? $quotation->ends_at ?? $start->copy()->addDay());
        $rateType = $this->rateTransactionType($transactionType);
        $rate = $manualUnitPrice === null && $product !== null
            ? $this->rateResolver->resolve($product, $rateType, $quotation->store_id, $start)
            : null;

        [$unitPrice, $subtotal, $rateDefinitionId, $rateBreakdown] = $this->subtotal(
            quotation: $quotation,
            quantity: $quantity,
            transactionType: $transactionType,
            rateType: $rateType,
            manualUnitPrice: $manualUnitPrice,
            rate: $rate,
            start: $start,
            end: $end,
        );
        $discounted = $this->applyDiscount($subtotal, $discountPercent, $quotation->currency_code);
        $tax = $quotation->prices_include_tax
            ? $this->taxResolver->calculateInclusive(
                $discounted,
                $quotation->currency_code,
                $quotation->customer?->sale_tax_class_id,
                $product?->tax_class_id,
                $start,
                $product?->sale_tax_category_id,
            )
            : $this->taxResolver->calculateExclusive(
                $discounted,
                $quotation->currency_code,
                $quotation->customer?->sale_tax_class_id,
                $product?->tax_class_id,
                $start,
                $product?->sale_tax_category_id,
            );

        return new QuotationLinePricing(
            unitPrice: $unitPrice,
            netTotal: $tax->netAmount,
            taxTotal: $tax->taxAmount,
            grossTotal: $tax->grossAmount,
            rateDefinitionId: $rateDefinitionId,
            taxRate: $tax->ratePercentage,
            breakdown: [
                'source' => $manualUnitPrice !== null ? 'manual' : ($rate !== null ? 'rate' : 'fallback'),
                'quantity' => $quantity,
                'discount_percent' => $discountPercent,
                'rate' => $rateBreakdown,
                'tax' => $tax->toArray(),
            ],
        );
    }

    /** @return array{int, int, int|null, array<string, mixed>|null} */
    private function subtotal(
        Quotation $quotation,
        string $quantity,
        LineItemTransactionType $transactionType,
        RateTransactionType $rateType,
        ?int $manualUnitPrice,
        ?ProductRate $rate,
        Carbon $start,
        Carbon $end,
    ): array {
        $unitPrice = $manualUnitPrice ?? ($rate !== null ? $rate->price : 0);

        if ($rate?->rateDefinition !== null && ! $this->quantityMinor->quantityHasFraction($quantity)) {
            $definition = $rate->rateDefinition;
            $context = new CalculationContext(
                unitPriceMinor: $unitPrice,
                currency: $quotation->currency_code,
                start: $start,
                end: $end,
                quantity: max(1, $this->quantityMinor->wholeUnitCountForRateEngine($quantity)),
                basePeriod: $definition->base_period,
                strategyConfig: $definition->strategy_config,
                transactionType: $rateType,
                storeId: $quotation->store_id,
            );
            $breakdown = $this->rateCalculator->calculate(
                $context,
                $definition->calculation_strategy->value,
                $definition->enabled_modifiers,
                $definition->modifier_configs,
            );

            return [$unitPrice, $breakdown->totalMinor(), $definition->id, $breakdown->toArray()];
        }

        $subtotal = $this->quantityMinor->multiplyMinorByQuantity($unitPrice, $quantity, $quotation->currency_code);

        if ($transactionType !== LineItemTransactionType::Sale) {
            $subtotal *= max(1, (int) $start->diffInDays($end));
        }

        return [$unitPrice, $subtotal, $rate?->rate_definition_id, null];
    }

    private function applyDiscount(int $subtotal, ?string $discountPercent, string $currency): int
    {
        if ($discountPercent === null || bccomp($discountPercent, '0', 4) <= 0) {
            return $subtotal;
        }

        $factor = bcsub('1', bcdiv($discountPercent, '100', 8), 8);

        return $this->rounding->roundMinorAmount(bcmul((string) $subtotal, $factor, 8), $currency);
    }

    private function rateTransactionType(LineItemTransactionType $type): RateTransactionType
    {
        return match ($type) {
            LineItemTransactionType::Sale => RateTransactionType::Sale,
            LineItemTransactionType::Service => RateTransactionType::Service,
            default => RateTransactionType::Rental,
        };
    }
}

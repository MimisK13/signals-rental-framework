<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\DealPriceMode;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for setting a manual deal-total override on an opportunity.
 */
class SetDealPriceData extends Data
{
    public function __construct(
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public int $deal_total = 0,
        public DealPriceMode $deal_price_mode = DealPriceMode::FlatHeadline,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'deal_total' => ['required', 'numeric', 'min:0'],
            'deal_price_mode' => ['sometimes', 'string', 'in:flat_headline,proportional'],
        ];
    }
}

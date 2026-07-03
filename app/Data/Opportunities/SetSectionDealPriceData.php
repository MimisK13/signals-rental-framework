<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class SetSectionDealPriceData extends Data
{
    public function __construct(
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public int $deal_price = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'deal_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}

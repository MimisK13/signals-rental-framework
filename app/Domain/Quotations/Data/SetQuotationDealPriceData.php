<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Data;

use App\Domain\Quotations\Enums\QuotationDealPriceMode;
use Spatie\LaravelData\Data;

final class SetQuotationDealPriceData extends Data
{
    public function __construct(
        public int $deal_total,
        public string $deal_price_mode = 'flat_headline',
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'deal_total' => ['required', 'integer', 'min:0'],
            'deal_price_mode' => ['sometimes', 'string', 'in:'.implode(',', array_column(QuotationDealPriceMode::cases(), 'value'))],
        ];
    }
}

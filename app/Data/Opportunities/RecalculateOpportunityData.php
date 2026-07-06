<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

class RecalculateOpportunityData extends Data
{
    public function __construct(
        public bool $reset_prices = false,
        public bool $reset_discounts = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'reset_prices' => ['sometimes', 'boolean'],
            'reset_discounts' => ['sometimes', 'boolean'],
        ];
    }
}

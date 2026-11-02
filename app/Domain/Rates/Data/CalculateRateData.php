<?php

declare(strict_types=1);

namespace App\Domain\Rates\Data;

use App\Domain\Rates\Enums\RateTransactionType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Data;

final class CalculateRateData extends Data
{
    public function __construct(
        public int $quantity,
        public string $start,
        public string $end,
        public RateTransactionType $transaction_type = RateTransactionType::Rental,
        public ?int $store_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'transaction_type' => ['sometimes', new Enum(RateTransactionType::class)],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
        ];
    }
}

<?php

namespace App\Data\Products;

use App\Enums\TransactionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class AdjustStockLevelData extends Data
{
    public function __construct(
        public int $transaction_type,
        public string $quantity,
        public string $reason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['sometimes', 'string'],
            'transaction_type' => ['required', 'integer', Rule::in(TransactionType::adjustmentValues())],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
            'store_id' => ['prohibited'],
            'actor_user_id' => ['prohibited'],
            'source_id' => ['prohibited'],
            'source_type' => ['prohibited'],
            'transaction_at' => ['prohibited'],
            'manual' => ['prohibited'],
            'description' => ['prohibited'],
        ];
    }
}

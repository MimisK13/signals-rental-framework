<?php

namespace App\Data\VirtualStock;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

class UpdateDraftVirtualStockIntakeData extends Data
{
    public function __construct(
        public int $product_id,
        public int $store_id,
        public int $quantity,
        public string $available_from,
        public string $available_until,
        public int $unit_cost,
        public ?string $currency_code = null,
        public ?int $purchase_order_id = null,
        public ?int $purchase_order_item_id = null,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(ValidationContext $context): array
    {
        return CreateVirtualStockIntakeData::rules($context);
    }
}

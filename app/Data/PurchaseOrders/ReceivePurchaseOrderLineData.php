<?php

namespace App\Data\PurchaseOrders;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ReceivePurchaseOrderLineData extends Data
{
    public function __construct(
        #[Required, Exists('purchase_order_items', 'id')]
        public int $purchase_order_item_id,
        #[Required]
        public string $quantity,
    ) {}
}

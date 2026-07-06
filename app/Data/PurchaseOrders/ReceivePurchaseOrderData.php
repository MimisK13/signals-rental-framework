<?php

namespace App\Data\PurchaseOrders;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ReceivePurchaseOrderData extends Data
{
    /**
     * @param  DataCollection<int, ReceivePurchaseOrderLineData>  $lines
     */
    public function __construct(
        #[Required, Min(1)]
        #[DataCollectionOf(ReceivePurchaseOrderLineData::class)]
        public DataCollection $lines,
    ) {}
}

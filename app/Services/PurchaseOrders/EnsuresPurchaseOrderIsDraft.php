<?php

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use Illuminate\Validation\ValidationException;

class EnsuresPurchaseOrderIsDraft
{
    public function assert(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_order' => ['Only draft purchase orders can be edited. Send the purchase order first to make it immutable.'],
            ]);
        }
    }
}

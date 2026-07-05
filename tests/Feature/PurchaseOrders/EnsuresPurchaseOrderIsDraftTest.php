<?php

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\EnsuresPurchaseOrderIsDraft;
use Illuminate\Validation\ValidationException;

it('allows draft purchase orders', function () {
    $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'draft']);

    app(EnsuresPurchaseOrderIsDraft::class)->assert($purchaseOrder);
})->throwsNoExceptions();

it('rejects sent purchase orders', function () {
    $purchaseOrder = PurchaseOrder::factory()->create(['status' => 'sent']);

    expect(fn () => app(EnsuresPurchaseOrderIsDraft::class)->assert($purchaseOrder))
        ->toThrow(ValidationException::class, 'Only draft purchase orders can be edited');
});

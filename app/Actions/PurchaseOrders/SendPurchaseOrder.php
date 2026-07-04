<?php

namespace App\Actions\PurchaseOrders;

use App\Concerns\CommitsVerbsEvents;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderEventRecorder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderSent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class SendPurchaseOrder
{
    use CommitsVerbsEvents;

    public function __invoke(PurchaseOrder $purchaseOrder, ?string $supplierReference = null): PurchaseOrderData
    {
        Gate::authorize('send', $purchaseOrder);

        $status = 'sent';
        app(PurchaseOrderStatusRegistry::class)->assertKnown($status);
        app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo($purchaseOrder->status, $status);

        $sentAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($purchaseOrder, $status, $sentAt, $supplierReference): void {
            PurchaseOrderSent::fire(
                state_id: (int) $purchaseOrder->state_id,
                status: $status,
                sent_at: $sentAt->toIso8601String(),
                supplier_reference: $supplierReference,
                supplier_reference_provided: $supplierReference !== null,
            );
        });

        $purchaseOrder = $purchaseOrder->refresh();
        app(PurchaseOrderEventRecorder::class)->sent($purchaseOrder);

        return PurchaseOrderData::fromModel($purchaseOrder);
    }
}

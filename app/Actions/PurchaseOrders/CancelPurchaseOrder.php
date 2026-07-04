<?php

namespace App\Actions\PurchaseOrders;

use App\Concerns\CommitsVerbsEvents;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderEventRecorder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCancelled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class CancelPurchaseOrder
{
    use CommitsVerbsEvents;

    public function __invoke(PurchaseOrder $purchaseOrder, ?string $reason = null): PurchaseOrderData
    {
        Gate::authorize('cancel', $purchaseOrder);

        $status = 'cancelled';
        app(PurchaseOrderStatusRegistry::class)->assertKnown($status);
        app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo($purchaseOrder->status, $status);

        $cancelledAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($purchaseOrder, $status, $cancelledAt, $reason): void {
            PurchaseOrderCancelled::fire(
                state_id: (int) $purchaseOrder->state_id,
                status: $status,
                cancelled_at: $cancelledAt->toIso8601String(),
                reason: $reason,
            );
        });

        $purchaseOrder = $purchaseOrder->refresh();
        app(PurchaseOrderEventRecorder::class)->cancelled($purchaseOrder, $reason);

        return PurchaseOrderData::fromModel($purchaseOrder);
    }
}

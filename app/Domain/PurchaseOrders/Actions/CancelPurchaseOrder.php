<?php

declare(strict_types=1);

namespace App\Domain\PurchaseOrders\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\PurchaseOrders\Data\PurchaseOrderData;
use App\Domain\PurchaseOrders\Models\PurchaseOrder;
use App\Domain\PurchaseOrders\Services\PurchaseOrderEventRecorder;
use App\Domain\PurchaseOrders\Services\PurchaseOrderStatusRegistry;
use App\Domain\PurchaseOrders\Verbs\Events\PurchaseOrderCancelled;
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
            $this->lockModelForMutation(
                $purchaseOrder,
                assert: fn (PurchaseOrder $locked) => app(PurchaseOrderStatusRegistry::class)
                    ->assertCanTransitionTo($locked->status, $status),
            );

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

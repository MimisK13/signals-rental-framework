<?php

namespace App\Actions\PurchaseOrders;

use App\Concerns\CommitsVerbsEvents;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\EnsuresPurchaseOrderIsDraft;
use App\Services\PurchaseOrders\PurchaseOrderEventRecorder;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderDeleted;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class DeleteDraftPurchaseOrder
{
    use CommitsVerbsEvents;

    public function __invoke(PurchaseOrder $purchaseOrder): void
    {
        Gate::authorize('delete', $purchaseOrder);
        app(EnsuresPurchaseOrderIsDraft::class)->assert($purchaseOrder);

        $deletedAt = CarbonImmutable::now('UTC');

        $this->commitVerbs(function () use ($purchaseOrder, $deletedAt): void {
            PurchaseOrderDeleted::fire(
                state_id: (int) $purchaseOrder->state_id,
                deleted_at: $deletedAt->toIso8601String(),
                deleted_by: auth()->id(),
            );
        });

        app(PurchaseOrderEventRecorder::class)->deleted($purchaseOrder);
    }
}

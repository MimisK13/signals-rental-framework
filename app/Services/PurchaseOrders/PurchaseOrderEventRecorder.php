<?php

namespace App\Services\PurchaseOrders;

use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Events\AuditableEvent;
use App\Models\PurchaseOrder;
use App\Services\Api\WebhookService;
use Thunk\Verbs\Facades\Verbs;

/**
 * Bridges purchase order mutations onto the audit log and outbound webhook bus.
 *
 * Emitted from actions after {@see CommitsVerbsEvents::commitVerbs()} — never
 * from Verbs apply()/handle() replay paths.
 */
class PurchaseOrderEventRecorder
{
    /**
     * @var list<string>
     */
    private const ENTITY_ACTIONS = [
        'purchase_order.created',
        'purchase_order.updated',
    ];

    public function created(PurchaseOrder $purchaseOrder): void
    {
        $this->record($purchaseOrder, 'purchase_order.created');
    }

    public function updated(PurchaseOrder $purchaseOrder): void
    {
        $this->record($purchaseOrder, 'purchase_order.updated');
    }

    public function sent(PurchaseOrder $purchaseOrder): void
    {
        $this->record($purchaseOrder, 'purchase_order.sent', [
            'status' => $purchaseOrder->status,
            'sent_at' => $purchaseOrder->sent_at?->toIso8601String(),
        ]);
    }

    public function received(PurchaseOrder $purchaseOrder): void
    {
        $this->record($purchaseOrder, 'purchase_order.received', [
            'status' => $purchaseOrder->status,
            'received_at' => $purchaseOrder->received_at?->toIso8601String(),
        ]);
    }

    public function cancelled(PurchaseOrder $purchaseOrder, ?string $reason = null): void
    {
        $this->record($purchaseOrder, 'purchase_order.cancelled', [
            'status' => $purchaseOrder->status,
            'cancelled_at' => $purchaseOrder->cancelled_at?->toIso8601String(),
            'reason' => $reason,
        ]);
    }

    public function deleted(PurchaseOrder $purchaseOrder): void
    {
        $this->record($purchaseOrder, 'purchase_order.deleted');
    }

    /**
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(PurchaseOrder $purchaseOrder, string $action, ?array $newValues = null): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        event(new AuditableEvent(
            model: $purchaseOrder,
            action: $action,
            newValues: $newValues,
        ));

        app(WebhookService::class)->dispatch($action, $this->payloadFor($purchaseOrder, $action));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(PurchaseOrder $purchaseOrder, string $action): array
    {
        if (in_array($action, self::ENTITY_ACTIONS, true)) {
            $fresh = PurchaseOrder::query()->find($purchaseOrder->getKey()) ?? $purchaseOrder;

            return ['purchase_order' => PurchaseOrderData::fromModel($fresh)->toArray()];
        }

        return [
            'id' => $purchaseOrder->getKey(),
            'action' => $action,
        ];
    }
}

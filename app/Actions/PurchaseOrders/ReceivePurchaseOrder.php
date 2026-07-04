<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\VirtualStock\ApportionVirtualStockCost;
use App\Concerns\CommitsVerbsEvents;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Data\PurchaseOrders\ReceivePurchaseOrderData;
use App\Enums\ShortageResolutionStatus;
use App\Enums\TransactionType;
use App\Models\OpportunityItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\VirtualStockIntake;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Services\PurchaseOrders\PurchaseOrderTaxCalculator;
use App\Services\SequenceAllocator;
use App\Services\Shortages\ItemShortageProbe;
use App\Services\Shortages\ShortageEventRecorder;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderReceiptRecorded;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeConfirmed;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeSupplyAdjusted;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeUpdated;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Facades\Verbs;

/**
 * Records goods received against a sent purchase order (P44).
 *
 * Composes PO receipt, virtual stock intake confirmation, stock transactions,
 * and shortage resolution fulfilment inside one Verbs transaction.
 */
class ReceivePurchaseOrder
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly PurchaseOrderTaxCalculator $taxCalculator,
        private readonly ShortageEventRecorder $shortageEvents,
        private readonly ItemShortageProbe $shortageProbe,
        private readonly ApportionVirtualStockCost $apportionVirtualStockCost,
    ) {}

    public function __invoke(PurchaseOrder $purchaseOrder, ReceivePurchaseOrderData $data): PurchaseOrderData
    {
        Gate::authorize('receive', $purchaseOrder);

        $purchaseOrder->loadMissing(['items.product', 'supplier', 'store']);

        if (! in_array($purchaseOrder->status, ['sent', 'partially_received'], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => ['Only sent or partially received purchase orders can receive goods.'],
            ]);
        }

        $itemsById = $purchaseOrder->items->keyBy('id');
        $receiptLines = [];
        $deltas = [];
        $cumulative = [];
        $stockReceipts = [];

        foreach ($data->lines as $line) {
            $item = $itemsById->get($line->purchase_order_item_id);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'lines' => ['One or more lines do not belong to this purchase order.'],
                ]);
            }

            $delta = bcadd($line->quantity, '0', 2);

            if (bccomp($delta, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'lines' => ['Receipt quantity must be greater than zero.'],
                ]);
            }

            $alreadyReceived = bcadd((string) $item->received_quantity, '0', 2);
            $remaining = bcsub((string) $item->quantity, $alreadyReceived, 2);

            if (bccomp($delta, $remaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'lines' => ["Line {$item->id} cannot receive more than the remaining quantity ({$remaining})."],
                ]);
            }

            $newCumulative = bcadd($alreadyReceived, $delta, 2);
            $cumulative[(int) $item->id] = $newCumulative;

            $deltas[] = [
                'purchase_order_item_id' => (int) $item->id,
                'received_quantity_delta' => $delta,
            ];

            $receiptLines[] = [
                'purchase_order_item_id' => (int) $item->id,
                'received_quantity' => $newCumulative,
                'unit_cost' => (int) $item->unit_cost,
                'product_tax_class_id' => $item->product_tax_class_id,
                'product_id' => $item->product_id,
            ];

            if ($purchaseOrder->po_type === 'purchase' && $item->product_id !== null) {
                $stockReceipts[] = [
                    'product_id' => (int) $item->product_id,
                    'store_id' => (int) $purchaseOrder->store_id,
                    'quantity' => $delta,
                    'description' => "PO {$purchaseOrder->number} receipt",
                ];
            }
        }

        $allFullyReceived = $purchaseOrder->items->every(function (PurchaseOrderItem $item) use ($cumulative): bool {
            $received = $cumulative[$item->id] ?? bcadd((string) $item->received_quantity, '0', 2);

            return bccomp($received, (string) $item->quantity, 2) === 0;
        });

        if ($allFullyReceived) {
            $resultingStatus = 'received';
        } elseif ($purchaseOrder->status === 'sent') {
            $resultingStatus = 'partially_received';
        } else {
            $resultingStatus = 'partially_received';
        }

        if ($resultingStatus !== $purchaseOrder->status) {
            app(PurchaseOrderStatusRegistry::class)->assertCanTransitionTo(
                $purchaseOrder->status,
                $resultingStatus,
            );
        }

        $receivedAt = CarbonImmutable::now('UTC');
        $taxFinalised = $allFullyReceived;
        $taxPayload = [
            'subtotal' => (int) $purchaseOrder->subtotal,
            'tax_total' => (int) $purchaseOrder->tax_total,
            'total' => (int) $purchaseOrder->total,
            'rounding_adjustment' => (int) $purchaseOrder->rounding_adjustment,
            'line_updates' => array_map(
                function (array $line) use ($itemsById): array {
                    $item = $itemsById->get($line['purchase_order_item_id']);

                    return [
                        'purchase_order_item_id' => $line['purchase_order_item_id'],
                        'received_quantity' => $line['received_quantity'],
                        'tax' => (int) $item->tax,
                        'total' => (int) $item->total,
                    ];
                },
                $receiptLines,
            ),
        ];

        if ($taxFinalised) {
            $actual = $this->taxCalculator->actualReceiptTotals(
                $purchaseOrder,
                $receiptLines,
                $receivedAt,
            );

            $this->taxCalculator->assertPennyReconciliation(
                $actual['subtotal'],
                $actual['tax_total'],
                $actual['rounding_adjustment'],
                $actual['total'],
                array_column($actual['lines'], 'total'),
            );

            $taxPayload = [
                'subtotal' => $actual['subtotal'],
                'tax_total' => $actual['tax_total'],
                'total' => $actual['total'],
                'rounding_adjustment' => $actual['rounding_adjustment'],
                'line_updates' => $actual['lines'],
            ];
        }

        $fullCumulative = [];
        foreach ($purchaseOrder->items as $item) {
            $fullCumulative[(int) $item->id] = $cumulative[$item->id]
                ?? bcadd((string) $item->received_quantity, '0', 2);
        }

        $intakeActions = $this->planIntakeActions($purchaseOrder, $fullCumulative, $receivedAt);
        $resolutionTargets = $this->linkedResolutions($purchaseOrder, $intakeActions);

        try {
            $this->commitVerbs(function () use (
                $purchaseOrder,
                $deltas,
                $fullCumulative,
                $resultingStatus,
                $receivedAt,
                $taxFinalised,
                $taxPayload,
                $intakeActions,
            ): void {
                PurchaseOrderReceiptRecorded::fire(
                    state_id: (int) $purchaseOrder->state_id,
                    lines: $deltas,
                    received_at: $receivedAt->toIso8601String(),
                    item_received_quantities: $fullCumulative,
                    resulting_status: $resultingStatus,
                    subtotal: $taxPayload['subtotal'],
                    tax_total: $taxPayload['tax_total'],
                    total: $taxPayload['total'],
                    rounding_adjustment: $taxPayload['rounding_adjustment'],
                    line_updates: $taxPayload['line_updates'],
                    tax_finalised: $taxFinalised,
                );

                $allocator = app(SequenceAllocator::class);

                foreach ($intakeActions as $action) {
                    $intake = $action['intake'];

                    if ($action['type'] === 'update_and_confirm') {
                        VirtualStockIntakeUpdated::fire(
                            state_id: (int) $intake->state_id,
                            purchase_order_id: $intake->purchase_order_id,
                            purchase_order_item_id: $intake->purchase_order_item_id,
                            product_id: (int) $intake->product_id,
                            store_id: (int) $intake->store_id,
                            quantity: $action['quantity'],
                            available_from: $intake->available_from->utc()->toIso8601String(),
                            available_until: $intake->available_until->utc()->toIso8601String(),
                            unit_cost: (int) $intake->unit_cost,
                            currency_code: $intake->currency_code,
                            notes: $intake->notes,
                            updated_at: $receivedAt->toIso8601String(),
                        );

                        VirtualStockIntakeConfirmed::fire(
                            state_id: (int) $intake->state_id,
                            status: 'confirmed',
                            demand_id: $allocator->next('demands'),
                            confirmed_at: $receivedAt->toIso8601String(),
                        );
                    }

                    if ($action['type'] === 'adjust_supply') {
                        VirtualStockIntakeSupplyAdjusted::fire(
                            state_id: (int) $intake->state_id,
                            quantity: $action['quantity'],
                            adjusted_at: $receivedAt->toIso8601String(),
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        foreach ($stockReceipts as $stockReceipt) {
            $this->recordStockReceipt($stockReceipt);
        }

        foreach ($resolutionTargets as $resolution) {
            $this->advanceResolution($resolution->fresh(), $resultingStatus);
        }

        foreach ($intakeActions as $action) {
            ($this->apportionVirtualStockCost)($action['intake']->refresh(), authorize: false);
        }

        $this->probeAffectedShortages($purchaseOrder, $resolutionTargets);

        return PurchaseOrderData::fromModel($purchaseOrder->refresh()->load('items'));
    }

    /**
     * @param  array<int, string>  $cumulativeReceived
     * @return list<array{type: string, intake: VirtualStockIntake, quantity: int}>
     */
    private function planIntakeActions(
        PurchaseOrder $purchaseOrder,
        array $cumulativeReceived,
        CarbonImmutable $receivedAt,
    ): array {
        if ($purchaseOrder->po_type !== 'sub_hire') {
            return [];
        }

        $actions = [];

        foreach ($purchaseOrder->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $intake = VirtualStockIntake::query()
                ->where('purchase_order_item_id', $item->id)
                ->where('status', 'draft')
                ->first();

            if ($intake === null) {
                $confirmedIntake = VirtualStockIntake::query()
                    ->where('purchase_order_item_id', $item->id)
                    ->whereIn('status', ['confirmed', 'received'])
                    ->first();

                if ($confirmedIntake === null) {
                    continue;
                }

                $targetQty = (int) round((float) ($cumulativeReceived[$item->id] ?? '0'));

                if ($targetQty > $confirmedIntake->quantity) {
                    $actions[] = [
                        'type' => 'adjust_supply',
                        'intake' => $confirmedIntake,
                        'quantity' => $targetQty,
                    ];
                }

                continue;
            }

            $targetQty = (int) round((float) ($cumulativeReceived[$item->id] ?? '0'));

            if ($targetQty <= 0) {
                continue;
            }

            $actions[] = [
                'type' => 'update_and_confirm',
                'intake' => $intake,
                'quantity' => $targetQty,
            ];
        }

        return $actions;
    }

    /**
     * @param  list<array{type: string, intake: VirtualStockIntake, quantity: int}>  $intakeActions
     * @return list<ShortageResolution>
     */
    private function linkedResolutions(PurchaseOrder $purchaseOrder, array $intakeActions): array
    {
        $resolutionIds = collect([$purchaseOrder->shortage_resolution_id])
            ->merge(
                collect($intakeActions)->map(
                    fn (array $action): ?int => ShortageResolution::query()
                        ->where('virtual_stock_intake_id', $action['intake']->id)
                        ->value('id'),
                ),
            )
            ->filter()
            ->unique()
            ->values();

        return ShortageResolution::query()
            ->whereIn('id', $resolutionIds)
            ->get()
            ->all();
    }

    private function advanceResolution(ShortageResolution $resolution, string $poStatus): void
    {
        $resolution->refresh();

        if ($resolution->status === ShortageResolutionStatus::Pending) {
            $resolution->forceFill([
                'status' => ShortageResolutionStatus::Confirmed,
                'confirmed_by' => auth()->id(),
                'confirmed_at' => Carbon::now('UTC'),
            ])->save();
            $this->shortageEvents->resolutionConfirmed($resolution);
            $resolution->refresh();
        }

        if ($resolution->status === ShortageResolutionStatus::Confirmed) {
            $resolution->forceFill([
                'status' => ShortageResolutionStatus::InProgress,
            ])->save();
            $this->shortageEvents->resolutionInProgress($resolution);
            $resolution->refresh();
        }

        $target = $poStatus === 'received'
            ? ShortageResolutionStatus::Fulfilled
            : ShortageResolutionStatus::PartiallyFulfilled;

        if ($resolution->status === ShortageResolutionStatus::InProgress
            && $resolution->status->canTransitionTo($target)) {
            $payload = ['status' => $target];

            if ($target === ShortageResolutionStatus::Fulfilled) {
                $payload['fulfilled_at'] = Carbon::now('UTC');
            }

            $resolution->forceFill($payload)->save();

            if ($target === ShortageResolutionStatus::Fulfilled) {
                $this->shortageEvents->resolutionFulfilled($resolution);
            }
        }
    }

    /**
     * @param  array{product_id: int, store_id: int, quantity: string, description: string}  $receipt
     */
    private function recordStockReceipt(array $receipt): void
    {
        $stockLevel = StockLevel::query()
            ->where('product_id', $receipt['product_id'])
            ->where('store_id', $receipt['store_id'])
            ->first();

        if ($stockLevel === null) {
            throw ValidationException::withMessages([
                'lines' => ['No stock level exists for a purchase receipt line.'],
            ]);
        }

        $quantity = bcadd($receipt['quantity'], '0', 2);

        if (bccomp($quantity, bcadd('0', '0', 2), 2) <= 0 || floor((float) $quantity) !== (float) $quantity) {
            throw ValidationException::withMessages([
                'lines' => ['Purchase receipt quantity must be a whole number.'],
            ]);
        }

        $transaction = StockTransaction::query()->create([
            'stock_level_id' => $stockLevel->id,
            'store_id' => $receipt['store_id'],
            'transaction_type' => TransactionType::Buy->value,
            'transaction_at' => now(),
            'quantity' => $quantity,
            'description' => $receipt['description'],
            'manual' => false,
        ]);

        $stockLevel->increment('quantity_held', (float) $transaction->signedQuantity());
    }

    /**
     * @param  list<ShortageResolution>  $resolutions
     */
    private function probeAffectedShortages(PurchaseOrder $purchaseOrder, array $resolutions): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $itemIds = ShortageResolution::query()
            ->whereIn('id', collect($resolutions)->pluck('id'))
            ->with('items')
            ->get()
            ->flatMap(fn (ShortageResolution $resolution) => $resolution->items->pluck('opportunity_item_id'))
            ->unique();

        if ($itemIds->isEmpty() && $purchaseOrder->opportunity_id !== null) {
            OpportunityItem::query()
                ->where('opportunity_id', $purchaseOrder->opportunity_id)
                ->each(fn (OpportunityItem $item) => $this->shortageProbe->probe($item));

            return;
        }

        OpportunityItem::query()
            ->whereIn('id', $itemIds)
            ->each(fn (OpportunityItem $item) => $this->shortageProbe->probe($item));
    }
}

<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\PurchaseOrders\Concerns\BuildsPurchaseOrderLinePayloads;
use App\Concerns\CommitsVerbsEvents;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Data\PurchaseOrders\UpdateDraftPurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Services\CurrencyService;
use App\Services\PurchaseOrders\EnsuresPurchaseOrderIsDraft;
use App\Services\PurchaseOrders\PurchaseOrderTotalsCalculator;
use App\Services\SequenceAllocator;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateDraftPurchaseOrder
{
    use BuildsPurchaseOrderLinePayloads;
    use CommitsVerbsEvents;

    public function __invoke(PurchaseOrder $purchaseOrder, UpdateDraftPurchaseOrderData $data): PurchaseOrderData
    {
        Gate::authorize('update', $purchaseOrder);
        app(EnsuresPurchaseOrderIsDraft::class)->assert($purchaseOrder);

        $currency = $data->currency !== null && $data->currency !== ''
            ? strtoupper($data->currency)
            : $purchaseOrder->currency_code;

        $updatedAt = CarbonImmutable::now('UTC');
        $allocator = app(SequenceAllocator::class);

        $preparedLines = $this->buildLinePayloads($data->lines, $data->supplier_id, $data->store_id, $allocator);

        $totals = app(PurchaseOrderTotalsCalculator::class)->calculate(
            $currency,
            array_map(fn (array $line): array => [
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'tax' => $line['tax'],
            ], $preparedLines),
            $data->rounding_adjustment,
        );

        $linePayloads = [];
        foreach ($preparedLines as $index => $line) {
            $computed = $totals['lines'][$index];
            $linePayloads[] = array_merge($line, [
                'total' => $computed['total'],
                'sort_order' => $computed['sort_order'],
                'received_quantity' => '0',
            ]);
        }

        $this->commitVerbs(function () use (
            $purchaseOrder,
            $data,
            $currency,
            $updatedAt,
            $linePayloads,
            $totals,
        ): void {
            PurchaseOrderUpdated::fire(
                state_id: (int) $purchaseOrder->state_id,
                po_type: $data->po_type,
                supplier_id: $data->supplier_id,
                store_id: $data->store_id,
                opportunity_id: $data->opportunity_id,
                shortage_resolution_id: $data->shortage_resolution_id,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency, $purchaseOrder),
                subtotal: $totals['subtotal'],
                tax_total: $totals['tax_total'],
                total: $totals['total'],
                rounding_adjustment: $data->rounding_adjustment,
                expected_at: $data->expected_at,
                starts_at: $data->starts_at,
                ends_at: $data->ends_at,
                reference: $data->reference,
                notes: $data->notes,
                supplier_reference: $data->supplier_reference,
                updated_at: $updatedAt->toIso8601String(),
                line_items: $linePayloads,
            );
        });

        return PurchaseOrderData::fromModel($purchaseOrder->refresh()->load('items'));
    }

    private function resolveExchangeRate(string $currency, PurchaseOrder $purchaseOrder): string
    {
        if ($currency === $purchaseOrder->currency_code) {
            return $purchaseOrder->exchange_rate;
        }

        $base = app(CurrencyService::class)->baseCurrencyCode();

        if ($base === '' || $currency === $base) {
            return '1';
        }

        try {
            return app(CurrencyService::class)->getRate($currency, $base);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'currency' => [
                    "No exchange rate is configured from {$currency} to the base currency {$base}.",
                ],
            ]);
        }
    }
}

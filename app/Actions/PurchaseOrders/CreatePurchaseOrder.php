<?php

namespace App\Actions\PurchaseOrders;

use App\Actions\PurchaseOrders\Concerns\BuildsPurchaseOrderLinePayloads;
use App\Concerns\CommitsVerbsEvents;
use App\Data\PurchaseOrders\CreatePurchaseOrderData;
use App\Data\PurchaseOrders\PurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Services\CurrencyService;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Services\PurchaseOrders\PurchaseOrderTaxCalculator;
use App\Services\PurchaseOrders\PurchaseOrderTotalsCalculator;
use App\Services\SequenceAllocator;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCreated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    use BuildsPurchaseOrderLinePayloads;
    use CommitsVerbsEvents;

    public function __invoke(CreatePurchaseOrderData $data): PurchaseOrderData
    {
        Gate::authorize('create', PurchaseOrder::class);

        $poTypes = array_keys(config('purchase_orders.types', []));
        if (! in_array($data->po_type, $poTypes, true)) {
            throw ValidationException::withMessages([
                'po_type' => ['The selected purchase order type is invalid.'],
            ]);
        }

        $status = app(PurchaseOrderStatusRegistry::class)->defaultStatus();
        app(PurchaseOrderStatusRegistry::class)->assertKnown($status);

        $currency = $this->resolveCurrency($data->currency);
        $createdAt = CarbonImmutable::now('UTC');
        $allocator = app(SequenceAllocator::class);

        $rawLines = $data->lines ?? [];
        $preparedLines = $this->buildLinePayloads($rawLines, $data->supplier_id, $data->store_id, $allocator);

        $preparedLines = app(PurchaseOrderTaxCalculator::class)->applyExpectedLineTax(
            $data->supplier_id,
            $data->store_id,
            $currency,
            $preparedLines,
            $createdAt,
        );

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

        $purchaseOrderId = $this->commitVerbs(function () use (
            $data,
            $status,
            $currency,
            $createdAt,
            $allocator,
            $linePayloads,
            $totals,
        ): int {
            $purchaseOrderId = $allocator->next('purchase_orders');

            $number = app(PurchaseOrderNumberGenerator::class)->nextOrCreate(
                $data->store_id,
                $createdAt,
            );

            PurchaseOrderCreated::fire(
                purchase_order_id: $purchaseOrderId,
                number: $number,
                po_type: $data->po_type,
                status: $status,
                supplier_id: $data->supplier_id,
                store_id: $data->store_id,
                opportunity_id: $data->opportunity_id,
                shortage_resolution_id: $data->shortage_resolution_id,
                currency_code: $currency,
                exchange_rate: $this->resolveExchangeRate($currency),
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
                created_by: auth()->id(),
                created_at: $createdAt->toIso8601String(),
                line_items: $linePayloads,
            );

            return $purchaseOrderId;
        });

        return PurchaseOrderData::fromModel(
            PurchaseOrder::query()->whereKey($purchaseOrderId)->firstOrFail(),
        );
    }

    private function resolveCurrency(?string $currency): string
    {
        if ($currency !== null && $currency !== '') {
            return strtoupper($currency);
        }

        return app(CurrencyService::class)->baseCurrencyCode();
    }

    private function resolveExchangeRate(string $currency): string
    {
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

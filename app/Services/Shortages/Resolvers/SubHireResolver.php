<?php

namespace App\Services\Shortages\Resolvers;

use App\Actions\PurchaseOrders\Concerns\BuildsPurchaseOrderLinePayloads;
use App\Concerns\CommitsVerbsEvents;
use App\Contracts\BatchShortageResolverContract;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Services\CurrencyService;
use App\Services\PurchaseOrders\PurchaseOrderNumberGenerator;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Services\PurchaseOrders\PurchaseOrderTotalsCalculator;
use App\Services\SequenceAllocator;
use App\Services\Shortages\ShortageEventRecorder;
use App\Services\Shortages\SubHireCostCalculator;
use App\Services\Shortages\SubHireSupplierCostQuery;
use App\Services\VirtualStock\VirtualStockNumberGenerator;
use App\Services\VirtualStock\VirtualStockStatusRegistry;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageGroup;
use App\Verbs\Events\PurchaseOrders\PurchaseOrderCreated;
use App\Verbs\Events\VirtualStock\VirtualStockIntakeCreated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Facades\Verbs;

/**
 * Sub-hire shortage resolver (Phase 4 / P43).
 *
 * Offers supplier-sourced sub-hire options from {@see ProductSupplierCost} rows.
 * apply() creates a draft sub_hire PO, a draft virtual stock intake linked to the
 * PO line, and a pending shortage resolution in one atomic Verbs transaction.
 * Availability lift is deferred until P44 receipt confirms the intake.
 */
class SubHireResolver extends AbstractShortageResolver implements BatchShortageResolverContract
{
    use BuildsPurchaseOrderLinePayloads;
    use CommitsVerbsEvents;

    public function __construct(
        ShortageEventRecorder $events,
        private readonly SubHireSupplierCostQuery $supplierCosts,
        private readonly SubHireCostCalculator $costCalculator,
    ) {
        parent::__construct($events);
    }

    public function key(): string
    {
        return 'subhire';
    }

    public function name(): string
    {
        return 'Sub-hire';
    }

    public function priority(): int
    {
        return 60;
    }

    public function isAutoExecutable(): bool
    {
        return (bool) config('shortages.resolvers.subhire.auto_executable', false);
    }

    public function supportsBatch(): bool
    {
        return true;
    }

    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0
            && $this->supplierCosts->hasCandidatesFor($shortage->productId, $shortage->storeId);
    }

    public function canResolveBatch(ShortageGroup $group): bool
    {
        if ($group->totalShortfall <= 0) {
            return false;
        }

        return $this->supplierCosts->hasCandidatesFor($group->productId, $group->storeId);
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getOptions(Shortage $shortage): array
    {
        if (! $this->canResolve($shortage)) {
            return [];
        }

        return $this->buildOptions(
            productId: $shortage->productId,
            productName: $shortage->productName,
            storeId: $shortage->storeId,
            quantity: $shortage->remainingShortfall(),
            startsAt: $shortage->startsAt,
            endsAt: $shortage->endsAt,
            isPartial: false,
            batchItemIds: null,
        );
    }

    /**
     * @return list<ResolutionOption>
     */
    public function getBatchOptions(ShortageGroup $group): array
    {
        if (! $this->canResolveBatch($group)) {
            return [];
        }

        return $this->buildOptions(
            productId: $group->productId,
            productName: $group->productName,
            storeId: $group->storeId,
            quantity: $group->totalShortfall,
            startsAt: $group->dateRangeStart,
            endsAt: $group->dateRangeEnd,
            isPartial: false,
            batchItemIds: $group->opportunityItemIds(),
        );
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        Gate::authorize('shortages.resolve');

        $resolution = $this->executeSubHire(
            allocations: [$shortage->opportunityItemId => $option->quantityResolved],
            productId: $shortage->productId,
            storeId: $shortage->storeId,
            opportunityId: $shortage->opportunityId,
            startsAt: $shortage->startsAt,
            endsAt: $shortage->endsAt,
            option: $option,
            eventShortage: $shortage,
        );

        return ResolutionResult::pending(
            $resolution,
            'Sub-hire PO and draft intake recorded; confirm on receipt (P44).',
            followupType: 'supplier_response',
        );
    }

    public function applyBatch(ShortageGroup $group, ResolutionOption $option): ResolutionResult
    {
        Gate::authorize('shortages.resolve');

        $allocations = [];

        foreach ($group->shortages as $shortage) {
            $allocations[$shortage->opportunityItemId] = $shortage->remainingShortfall();
        }

        $primary = $group->shortages[0];

        $resolution = $this->executeSubHire(
            allocations: $allocations,
            productId: $group->productId,
            storeId: $group->storeId,
            opportunityId: $primary->opportunityId,
            startsAt: $group->dateRangeStart,
            endsAt: $group->dateRangeEnd,
            option: $option,
            eventShortage: $primary,
        );

        return ResolutionResult::pending(
            $resolution,
            'Batch sub-hire PO and draft intake recorded across '.$group->opportunityCount().' job(s).',
            followupType: 'supplier_response',
        );
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Subhire;
    }

    /**
     * @param  list<int>|null  $batchItemIds
     * @return list<ResolutionOption>
     */
    private function buildOptions(
        int $productId,
        string $productName,
        int $storeId,
        int $quantity,
        Carbon $startsAt,
        Carbon $endsAt,
        bool $isPartial,
        ?array $batchItemIds,
    ): array {
        $options = [];
        $autoExecutable = $this->isAutoExecutable();

        foreach ($this->supplierCosts->candidatesFor($productId, $storeId) as $supplierCost) {
            $supplierName = $supplierCost->supplier->name ?? 'Supplier #'.$supplierCost->supplier_id;
            $basisLabel = config(
                "supplier_costs.cost_bases.{$supplierCost->cost_basis}.label",
                $supplierCost->cost_basis,
            );
            $estimatedCost = $this->costCalculator->estimatedTotal(
                $supplierCost,
                $quantity,
                $startsAt,
                $endsAt,
            );
            $leadDays = $supplierCost->lead_time_days;
            $leadMinutes = $leadDays !== null ? $leadDays * 24 * 60 : null;
            $preferredLabel = $supplierCost->is_preferred ? ' (preferred)' : '';

            $options[] = new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::Subhire,
                label: "Sub-hire {$quantity} from {$supplierName}{$preferredLabel}",
                description: sprintf(
                    '%s · %s basis · covers %d unit(s)',
                    $productName,
                    is_string($basisLabel) ? $basisLabel : $supplierCost->cost_basis,
                    $quantity,
                ),
                quantityResolved: $quantity,
                isPartial: $isPartial,
                autoExecutable: $autoExecutable,
                estimatedCost: $estimatedCost,
                estimatedLeadTimeMinutes: $leadMinutes,
                requiresConfirmation: true,
                metadata: [
                    'supplier_id' => $supplierCost->supplier_id,
                    'supplier_cost_id' => $supplierCost->id,
                    'supplier_name' => $supplierName,
                    'cost_basis' => $supplierCost->cost_basis,
                    'is_preferred' => $supplierCost->is_preferred,
                    'currency_code' => $supplierCost->currency_code,
                    'batch_opportunity_item_ids' => $batchItemIds,
                ],
            );
        }

        return $options;
    }

    /**
     * @param  array<int, int>  $allocations
     */
    private function executeSubHire(
        array $allocations,
        int $productId,
        int $storeId,
        int $opportunityId,
        Carbon $startsAt,
        Carbon $endsAt,
        ResolutionOption $option,
        Shortage $eventShortage,
    ): ShortageResolution {
        $supplierId = (int) ($option->metadata['supplier_id'] ?? 0);
        $supplierCostId = (int) ($option->metadata['supplier_cost_id'] ?? 0);

        if ($supplierId <= 0 || $supplierCostId <= 0) {
            throw ValidationException::withMessages([
                'option' => ['The selected sub-hire option is missing supplier provenance.'],
            ]);
        }

        /** @var ProductSupplierCost $supplierCost */
        $supplierCost = ProductSupplierCost::query()->findOrFail($supplierCostId);
        $quantityResolved = array_sum($allocations);
        $product = Product::query()->findOrFail($productId);
        $currency = $supplierCost->currency_code !== ''
            ? strtoupper($supplierCost->currency_code)
            : app(CurrencyService::class)->baseCurrencyCode();

        try {
            return ShortageResolution::query()->getModel()->getConnection()->transaction(function () use (
                $allocations,
                $productId,
                $storeId,
                $opportunityId,
                $startsAt,
                $endsAt,
                $option,
                $eventShortage,
                $supplierId,
                $supplierCost,
                $quantityResolved,
                $product,
                $currency,
            ): ShortageResolution {
                OpportunityItem::query()
                    ->whereIn('id', array_keys($allocations))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $resolution = ShortageResolution::query()->create([
                    'resolver_key' => $this->key(),
                    'resolution_type' => $this->resolutionType()->value,
                    'status' => ShortageResolutionStatus::Pending->value,
                    'quantity_resolved' => $quantityResolved,
                    'cost' => $option->estimatedCost,
                    'metadata' => [
                        'product_id' => $productId,
                        'store_id' => $storeId,
                        'opportunity_id' => $opportunityId,
                        'supplier_id' => $supplierId,
                        'supplier_cost_id' => $supplierCost->id,
                        'cost_basis' => $supplierCost->cost_basis,
                        'pending_dependency' => 'sub_hire_receipt',
                    ],
                    'resolved_by' => auth()->id(),
                ]);

                foreach ($allocations as $itemId => $allocatedQty) {
                    ShortageResolutionItem::query()->create([
                        'shortage_resolution_id' => $resolution->id,
                        'opportunity_item_id' => $itemId,
                        'quantity_allocated' => $allocatedQty,
                    ]);
                }

                $allocator = app(SequenceAllocator::class);
                $createdAt = CarbonImmutable::now('UTC');
                $lineQuantity = $this->costCalculator->purchaseOrderQuantity(
                    $supplierCost,
                    $quantityResolved,
                );
                $unitCost = $this->costCalculator->purchaseOrderUnitCost(
                    $supplierCost,
                    $startsAt,
                    $endsAt,
                );

                $preparedLines = $this->buildLinePayloads(
                    [[
                        'product_id' => $productId,
                        'description' => $product->name,
                        'quantity' => $lineQuantity,
                        'unit_cost' => $unitCost,
                        'supplier_cost_id' => $supplierCost->id,
                    ]],
                    $supplierId,
                    $storeId,
                    $allocator,
                );

                $totals = app(PurchaseOrderTotalsCalculator::class)->calculate(
                    $currency,
                    array_map(fn (array $line): array => [
                        'quantity' => $line['quantity'],
                        'unit_cost' => $line['unit_cost'],
                        'tax' => $line['tax'],
                    ], $preparedLines),
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

                $purchaseOrderId = $allocator->next('purchase_orders');
                $poItemId = (int) $linePayloads[0]['purchase_order_item_id'];

                PurchaseOrderCreated::fire(
                    purchase_order_id: $purchaseOrderId,
                    number: app(PurchaseOrderNumberGenerator::class)->nextOrCreate($storeId, $createdAt),
                    po_type: 'sub_hire',
                    status: app(PurchaseOrderStatusRegistry::class)->defaultStatus(),
                    supplier_id: $supplierId,
                    store_id: $storeId,
                    opportunity_id: $opportunityId,
                    shortage_resolution_id: $resolution->id,
                    currency_code: $currency,
                    exchange_rate: '1',
                    subtotal: $totals['subtotal'],
                    tax_total: $totals['tax_total'],
                    total: $totals['total'],
                    starts_at: $startsAt->utc()->toIso8601String(),
                    ends_at: $endsAt->utc()->toIso8601String(),
                    created_by: auth()->id(),
                    created_at: $createdAt->toIso8601String(),
                    line_items: $linePayloads,
                );

                $intakeStatus = app(VirtualStockStatusRegistry::class)->defaultStatus();
                app(VirtualStockStatusRegistry::class)->assertKnown($intakeStatus);

                $intakeId = $allocator->next('virtual_stock_intakes');

                VirtualStockIntakeCreated::fire(
                    virtual_stock_intake_id: $intakeId,
                    number: app(VirtualStockNumberGenerator::class)->nextOrCreate($storeId, $createdAt),
                    status: $intakeStatus,
                    purchase_order_id: $purchaseOrderId,
                    purchase_order_item_id: $poItemId,
                    primary_opportunity_id: $opportunityId,
                    product_id: $productId,
                    store_id: $storeId,
                    quantity: max(1, (int) round((float) $lineQuantity)),
                    available_from: $startsAt->utc()->toIso8601String(),
                    available_until: $endsAt->utc()->toIso8601String(),
                    unit_cost: $unitCost,
                    currency_code: $currency,
                    created_by: auth()->id(),
                    created_at: $createdAt->toIso8601String(),
                );

                Verbs::commit();

                $resolution->update([
                    'virtual_stock_intake_id' => $intakeId,
                    'metadata' => $resolution->metadata + [
                        'purchase_order_id' => $purchaseOrderId,
                        'purchase_order_item_id' => $poItemId,
                    ],
                ]);

                $resolution = $resolution->fresh(['items']);

                $this->events->resolutionCreated($resolution, $eventShortage);

                return $resolution;
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }
    }
}

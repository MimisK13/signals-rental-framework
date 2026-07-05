<?php

namespace App\Actions\Containers\Concerns;

use App\Actions\Opportunities\Concerns\FormatsOpportunityDates;
use App\Data\Containers\ContainerDispatchAmendmentData;
use App\Data\Containers\ContainerDispatchProductVerdictData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerDispatchVerdict;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Services\Containers\ContainerManifestService;
use App\Services\Opportunities\ItemTreeService;
use App\Services\Opportunities\OpportunityAutoGroupResolver;
use App\Services\SequenceAllocator;
use App\Services\Shortages\DispatchShortageGate;
use App\ValueObjects\DispatchGateResult;
use App\Verbs\Events\Opportunities\AssetAllocated;
use App\Verbs\Events\Opportunities\AssetContainerSet;
use App\Verbs\Events\Opportunities\AssetDispatched;
use App\Verbs\Events\Opportunities\ItemAdded;
use App\Verbs\Events\Opportunities\ItemQuantityChanged;
use App\Verbs\States\AssetAssignmentState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Shared Verbs orchestration for dispatching manifest assets to an opportunity.
 *
 * Used by transport dissolve-on-dispatch and kit/hybrid persistent dispatch.
 */
trait DispatchesOpportunityContainerAssets
{
    use FormatsOpportunityDates;

    /** @var DispatchGateResult|null Last shortage-gate outcome for WarnPartial metadata. */
    public ?DispatchGateResult $gateResult = null;

    /**
     * Lines added inside the current Verbs batch (projection not yet committed).
     *
     * @var array<int, list<array{id: int, quantity: string, batch_allocated: int}>>
     */
    private array $pendingLinesByProduct = [];

    /**
     * @param  array{
     *     auto_adds: list<ContainerDispatchProductVerdictData>,
     * }  $validation
     * @param  list<ContainerDispatchAmendmentData>  $approvedAmendments
     * @param  list<array<string, mixed>>  $autoAdded
     * @param  list<array<string, mixed>>  $amended
     */
    protected function applyAmendments(
        Opportunity $opportunity,
        array $validation,
        array $approvedAmendments,
        array &$autoAdded,
        array &$amended,
    ): void {
        foreach ($validation['auto_adds'] as $row) {
            $this->fireItemAdded(
                opportunity: $opportunity,
                productId: $row->product_id,
                quantity: $row->suggested_quantity ?? (string) $row->container_quantity,
                unitPrice: $row->suggested_price,
            );

            $autoAdded[] = [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => $row->suggested_quantity ?? (string) $row->container_quantity,
                'price' => $row->suggested_price,
            ];
        }

        foreach ($approvedAmendments as $amendment) {
            if ($amendment->action === 'add') {
                $product = Product::query()->whereKey($amendment->product_id)->firstOrFail();

                $this->fireItemAdded(
                    opportunity: $opportunity,
                    productId: $amendment->product_id,
                    quantity: $amendment->quantity,
                    unitPrice: $amendment->price,
                    name: $product->name,
                );
            } else {
                $line = $this->firstLineForProduct($opportunity, $amendment->product_id);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        'amendments' => [__('No line item exists to increase for product :id.', ['id' => $amendment->product_id])],
                    ]);
                }

                ItemQuantityChanged::fire(
                    opportunity_item_id: $line->state_id,
                    quantity: $amendment->quantity,
                );
            }

            $amended[] = [
                'product_id' => $amendment->product_id,
                'action' => $amendment->action,
                'quantity' => $amendment->quantity,
                'price' => $amendment->price,
            ];
        }
    }

    protected function dispatchManifestAsset(
        Opportunity $opportunity,
        int $serialisedItemId,
        int $productId,
        ?int $containerStockLevelId,
        string $dispatchedAt,
        ?int $lineProductId = null,
        bool $ignoreAvailability = false,
    ): int {
        $lineProductId ??= $productId;

        $assignment = OpportunityItemAsset::query()
            ->where('stock_level_id', $serialisedItemId)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunity->id))
            ->first();

        if ($assignment !== null) {
            $stateId = $assignment->state_id;
            $assignmentId = $assignment->id;
            $item = $assignment->item()->firstOrFail();
        } else {
            $line = $this->findLineWithCapacity($opportunity, $lineProductId);

            if ($line === null) {
                throw ValidationException::withMessages([
                    'dispatch' => [__('No line item with remaining capacity for product :id.', ['id' => $lineProductId])],
                ]);
            }

            $assignmentId = app(SequenceAllocator::class)->next('opportunity_item_assets');

            $allocated = AssetAllocated::fire(
                assignment_id: $assignmentId,
                opportunity_item_id: $line->id,
                stock_level_id: $serialisedItemId,
                allocated_at: $dispatchedAt,
                ignore_availability: $ignoreAvailability,
            );

            $assignmentState = $allocated->state(AssetAssignmentState::class);

            if (! $assignmentState instanceof AssetAssignmentState) {
                throw ValidationException::withMessages([
                    'dispatch' => [__('Unable to resolve the asset assignment state for stock level :id.', ['id' => $serialisedItemId])],
                ]);
            }

            $stateId = (int) $assignmentState->id;
            $assignmentId = $assignmentState->assignment_id;
            $item = $line;
        }

        $this->gateResult = app(DispatchShortageGate::class)->enforceForItem($item);

        AssetDispatched::fire(
            state_id: $stateId,
            dispatched_by: Auth::id(),
            dispatched_at: $dispatchedAt,
        );

        if ($containerStockLevelId !== null) {
            AssetContainerSet::fire(
                state_id: $stateId,
                container_stock_level_id: $containerStockLevelId,
            );
        }

        return $assignmentId;
    }

    protected function fireItemAdded(
        Opportunity $opportunity,
        int $productId,
        string $quantity,
        ?int $unitPrice = null,
        ?string $name = null,
    ): void {
        $product = Product::query()->whereKey($productId)->firstOrFail();
        $now = Carbon::now('UTC');
        $startsAt = $this->toIso($opportunity->starts_at) ?? $now->toIso8601String();
        $endsAt = $this->toIso($opportunity->ends_at)
            ?? Carbon::parse($startsAt)->copy()->addDay()->toIso8601String();
        $versionId = $opportunity->active_version_id > 0 ? $opportunity->active_version_id : null;
        $itemId = app(SequenceAllocator::class)->next('opportunity_items');
        $path = app(ItemTreeService::class)->nextTopLevelPath($opportunity->id, $versionId);

        $stub = new OpportunityItem([
            'itemable_id' => $productId,
            'itemable_type' => Product::class,
            'transaction_type' => LineItemTransactionType::Rental->value,
        ]);
        [$revenueGroupId] = app(OpportunityAutoGroupResolver::class)->resolve($stub);

        ItemAdded::fire(
            opportunity_item_id: $itemId,
            opportunity_id: $opportunity->id,
            version_id: $versionId,
            itemable_id: $productId,
            itemable_type: Product::class,
            item_type: OpportunityItemType::Product->value,
            path: $path,
            revenue_group_id: $revenueGroupId,
            name: $name ?? $product->name,
            quantity: $quantity,
            transaction_type: LineItemTransactionType::Rental->value,
            starts_at: $startsAt,
            ends_at: $endsAt,
            manual_unit_price: $unitPrice,
        );

        $this->pendingLinesByProduct[$productId] ??= [];
        $this->pendingLinesByProduct[$productId][] = [
            'id' => $itemId,
            'quantity' => $quantity,
            'batch_allocated' => 0,
        ];
    }

    protected function findLineWithCapacity(Opportunity $opportunity, int $productId): ?OpportunityItem
    {
        foreach ($this->pendingLinesByProduct[$productId] ?? [] as &$pending) {
            $allocated = OpportunityItemAsset::query()
                ->where('opportunity_item_id', $pending['id'])
                ->count() + $pending['batch_allocated'];

            $maxAssets = (int) ceil((float) $pending['quantity']);

            if ($allocated < $maxAssets) {
                $pending['batch_allocated']++;

                return new OpportunityItem([
                    'id' => $pending['id'],
                    'opportunity_id' => $opportunity->id,
                    'itemable_id' => $productId,
                    'itemable_type' => Product::class,
                    'quantity' => $pending['quantity'],
                ]);
            }
        }
        unset($pending);

        $lines = $opportunity->items
            ->filter(fn (OpportunityItem $item): bool => $item->itemable_type === Product::class
                && (int) $item->itemable_id === $productId);

        foreach ($lines as $line) {
            $allocated = OpportunityItemAsset::query()
                ->where('opportunity_item_id', $line->id)
                ->count();

            $maxAssets = (int) ceil((float) $line->quantity);

            if ($allocated < $maxAssets) {
                return $line;
            }
        }

        return null;
    }

    protected function firstLineForProduct(Opportunity $opportunity, int $productId): ?OpportunityItem
    {
        return $opportunity->items
            ->first(fn (OpportunityItem $item): bool => $item->itemable_type === Product::class
                && (int) $item->itemable_id === $productId);
    }

    /**
     * @return array{
     *     product_id: int,
     *     product_name: string,
     *     verdict: string,
     *     reason: string|null,
     *     container_quantity: int,
     *     opportunity_quantity: string,
     *     unallocated_quantity: int,
     *     required_action: string|null,
     *     suggested_quantity: string|null,
     *     suggested_price: int|null,
     * }
     */
    protected function promptPayload(ContainerDispatchProductVerdictData $row): array
    {
        return [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'verdict' => ContainerDispatchVerdict::Prompt->value,
            'reason' => $row->reason,
            'container_quantity' => $row->container_quantity,
            'opportunity_quantity' => $row->opportunity_quantity,
            'unallocated_quantity' => $row->unallocated_quantity,
            'required_action' => $row->required_action,
            'suggested_quantity' => $row->suggested_quantity,
            'suggested_price' => $row->suggested_price,
        ];
    }

    /**
     * @param  list<array{serialised_item_id: int, product_id: int, container_stock_level_id: int|null, line_product_id?: int}>  $assets
     * @return array<int, AssetAssignmentStatus>
     */
    protected function dispatchAssetManifest(
        Opportunity $opportunity,
        array $assets,
        string $dispatchedAt,
        bool $ignoreAvailability = false,
    ): array {
        $overlay = [];

        foreach ($assets as $assetManifest) {
            $assignmentId = $this->dispatchManifestAsset(
                opportunity: $opportunity,
                serialisedItemId: (int) $assetManifest['serialised_item_id'],
                productId: (int) $assetManifest['product_id'],
                containerStockLevelId: $assetManifest['container_stock_level_id'],
                dispatchedAt: $dispatchedAt,
                lineProductId: isset($assetManifest['line_product_id'])
                    ? (int) $assetManifest['line_product_id']
                    : (int) $assetManifest['product_id'],
                ignoreAvailability: $ignoreAvailability,
            );

            $overlay[$assignmentId] = AssetAssignmentStatus::Dispatched;
        }

        return $overlay;
    }

    /**
     * Auto-add opportunity lines for fixed kit/hybrid contents that are not booked
     * as separate customer-facing lines (serialised-containers.md §"Kit Dispatch").
     *
     * @return list<array<string, mixed>>
     */
    protected function ensureKitContentLines(Opportunity $opportunity, Container $container): array
    {
        $manifests = app(ContainerManifestService::class);
        $quantities = $manifests->kitContentProductQuantities($container);
        $autoAdded = [];

        foreach ($quantities as $productId => $quantity) {
            $productId = (int) $productId;

            if ($container->product_id !== null && $productId === (int) $container->product_id) {
                continue;
            }

            if ($container->availabilityMode() === ContainerAvailabilityMode::Hybrid
                && ! $manifests->isFixedBinding($container, $productId)) {
                continue;
            }

            if ($this->firstLineForProduct($opportunity, $productId) !== null) {
                continue;
            }

            $product = Product::query()->whereKey($productId)->firstOrFail();

            $this->fireItemAdded(
                opportunity: $opportunity,
                productId: $productId,
                quantity: (string) $quantity,
                unitPrice: 0,
                name: $product->name,
            );

            $autoAdded[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'quantity' => (string) $quantity,
                'price' => 0,
            ];
        }

        return $autoAdded;
    }
}

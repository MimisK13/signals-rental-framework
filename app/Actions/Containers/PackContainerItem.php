<?php

namespace App\Actions\Containers;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerItemData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\PackContainerItemResultData;
use App\Data\Containers\TransferContainerItemData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\PackContainerResolution;
use App\Events\Containers\BeforeContainerPacked;
use App\Events\Containers\ContainerPacked;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Availability\ContainerDemandResolver;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Services\AvailabilityService;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Containers\ContainerManifestService;
use App\Services\Containers\ContainerSubHireNotice;
use App\Verbs\Events\Opportunities\AssetReturned;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pack a serialised item into an open container with warn-and-resolve conflict
 * handling (serialised-containers.md §Containment Rules / §Pack Endpoint).
 *
 * Hard blocks remain for data-integrity invariants (wrong store, non-serialised,
 * closed target container, nesting depth exceeded). Conflicts return HTTP 409 with
 * structured resolutions: transfer, auto_return_and_pack, confirm_nesting.
 */
class PackContainerItem
{
    use CommitsVerbsEvents, RebuildsAvailabilitySnapshots;

    public function __invoke(Container $container, PackContainerItemData $data): PackContainerItemResultData
    {
        Gate::authorize('pack', $container);

        $stockLevel = StockLevel::query()->findOrFail($data->serialised_item_id);
        $resolve = $data->resolve !== null ? PackContainerResolution::tryFrom($data->resolve) : null;

        if ($resolve === PackContainerResolution::Transfer) {
            return $this->resolveTransfer($container, $data, $stockLevel);
        }

        $this->guardHardInvariants($container, $stockLevel, $resolve);

        if ($resolve === PackContainerResolution::AutoReturnAndPack) {
            Gate::authorize('packOverride', $container);

            return $this->autoReturnAndPack($container, $data, $stockLevel);
        }

        $this->guardConflicts($container, $stockLevel, $resolve);

        $item = DB::transaction(fn (): ContainerItem => $this->executePack(
            $container,
            $stockLevel,
            $data->position,
            $data->notes,
            confirmedNesting: $resolve === PackContainerResolution::ConfirmNesting,
        ));

        return $this->buildResult($container, $item);
    }

    /**
     * Core pack write — membership row, CRMS mirror, container demand, events.
     * Called directly by {@see TransferContainerItem} after the source unpack.
     */
    public function executePack(
        Container $container,
        StockLevel $stockLevel,
        ?string $position = null,
        ?string $notes = null,
        ?int $autoReturnedFromOpportunityId = null,
        bool $confirmedNesting = false,
        ?int $returnedFromOpportunityId = null,
    ): ContainerItem {
        $lockedContainer = Container::query()
            ->whereKey($container->id)
            ->lockForUpdate()
            ->first();

        if ($lockedContainer === null) {
            throw ValidationException::withMessages([
                'container' => __('The target container no longer exists.'),
            ]);
        }

        if (! $lockedContainer->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Items can only be packed into an open container.'),
            ]);
        }

        $container = $lockedContainer;

        app(ProductStoreCapacityLock::class)->acquire($stockLevel->product_id, $stockLevel->store_id);

        // Re-check after taking the capacity lock so an allocation committed
        // concurrently with the initial conflict read cannot be packed into a
        // container. Checked-in/finalised assignments remain valid here for the
        // existing repack-on-return path.
        $this->guardPreDispatchAssignment($stockLevel);

        BeforeContainerPacked::dispatch($container, $stockLevel);

        $item = new ContainerItem([
            'container_id' => $container->id,
            'serialised_item_id' => $stockLevel->id,
            'product_id' => $stockLevel->product_id,
            'packed_at' => Carbon::now('UTC'),
            'packed_by_user_id' => Auth::id(),
            'position' => $position,
            'notes' => $notes,
            'auto_returned_from_opportunity_id' => $autoReturnedFromOpportunityId,
            'returned_from_opportunity_id' => $returnedFromOpportunityId,
        ]);

        try {
            $item->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is already packed in an active container.'),
            ]);
        }

        if ($container->serialised_item_id !== null) {
            $stockLevel->forceFill([
                'container_stock_level_id' => $container->serialised_item_id,
                'container_mode' => $container->availabilityMode()->value,
            ])->save();
        }

        $item->setRelation('container', $container);
        app(ContainerDemandResolver::class)->syncDemands($item);
        app(ContainerEventRecorder::class)->itemPacked($container, $item);
        ContainerPacked::dispatch($container, $item);

        if ($confirmedNesting) {
            $nested = Container::query()
                ->active()
                ->where('serialised_item_id', $stockLevel->id)
                ->first();

            if ($nested !== null) {
                $nested->update(['parent_container_id' => $container->id]);
                $depth = app(ContainerManifestService::class)->nestingDepth($container, $nested);

                app(ContainerEventRecorder::class)->nested($container, $nested, $depth);
            }
        }

        return $item;
    }

    protected function resolveTransfer(
        Container $target,
        PackContainerItemData $data,
        StockLevel $stockLevel,
    ): PackContainerItemResultData {
        Gate::authorize('transfer', $target);

        $existing = ContainerItem::query()
            ->where('serialised_item_id', $stockLevel->id)
            ->whereNull('unpacked_at')
            ->first();

        if ($existing === null) {
            throw ValidationException::withMessages([
                'resolve' => __('No active container membership exists to transfer from.'),
            ]);
        }

        $item = (new TransferContainerItem)($target, TransferContainerItemData::from([
            'serialised_item_id' => $stockLevel->id,
            'source_container_id' => $existing->container_id,
            'position' => $data->position,
            'notes' => $data->notes,
        ]));

        $notice = app(ContainerSubHireNotice::class)->forStockLevel($stockLevel);

        return new PackContainerItemResultData(
            container_item: $item,
            notices: $notice !== null ? [$notice] : [],
        );
    }

    protected function autoReturnAndPack(
        Container $container,
        PackContainerItemData $data,
        StockLevel $stockLevel,
    ): PackContainerItemResultData {
        $assignment = $this->findCheckedOutAssignment($stockLevel);

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'resolve' => __('The item is not checked out on an opportunity.'),
            ]);
        }

        $opportunityId = (int) $assignment->item?->opportunity_id;
        $returnedAt = Carbon::now('UTC')->toIso8601String();

        $item = DB::transaction(function () use ($container, $data, $stockLevel, $assignment, $opportunityId, $returnedAt): ContainerItem {
            $this->commitVerbs(function () use ($assignment, $returnedAt): void {
                AssetReturned::fire(
                    state_id: $assignment->state_id,
                    returned_at: $returnedAt,
                );
            });

            if ($assignment->item !== null) {
                $this->rebuildSnapshotsForItems([$assignment->item]);
            }

            return $this->executePack(
                $container,
                $stockLevel,
                $data->position,
                $data->notes,
                autoReturnedFromOpportunityId: $opportunityId > 0 ? $opportunityId : null,
            );
        });

        return $this->buildResult($container, $item);
    }

    /**
     * @throws ValidationException
     */
    protected function guardHardInvariants(
        Container $container,
        StockLevel $stockLevel,
        ?PackContainerResolution $resolve,
    ): void {
        if (! $container->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Items can only be packed into an open container.'),
            ]);
        }

        if (! $stockLevel->isSerialised()) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Only serialised items can be packed into a container.'),
            ]);
        }

        if (
            $container->store_id !== null
            && (int) $stockLevel->store_id !== (int) $container->store_id
        ) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is held at a different store to the container.'),
            ]);
        }

        if ($resolve !== PackContainerResolution::ConfirmNesting) {
            $this->guardNestingDepthExceeded($container, $stockLevel);
        } else {
            Gate::authorize('nest', $container);
            $this->guardNestingDepthExceeded($container, $stockLevel);
        }
    }

    protected function guardConflicts(
        Container $container,
        StockLevel $stockLevel,
        ?PackContainerResolution $resolve,
    ): void {
        $existing = ContainerItem::query()
            ->where('serialised_item_id', $stockLevel->id)
            ->whereNull('unpacked_at')
            ->with('container')
            ->first();

        if ($existing !== null && (int) $existing->container_id !== (int) $container->id) {
            throw $this->alreadyInContainerConflict($container, $existing);
        }

        $this->guardPreDispatchAssignment($stockLevel);

        if ($assignment = $this->findCheckedOutAssignment($stockLevel)) {
            throw $this->checkedOutConflict($container, $assignment);
        }

        if ($resolve !== PackContainerResolution::ConfirmNesting) {
            $this->guardNestingConfirmation($container, $stockLevel);
        }

        if (! app(AvailabilityService::class)->checkAssetAvailable(
            $stockLevel->id,
            Carbon::now('UTC'),
            Demand::sentinel(),
        )) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('This item is currently committed to an opportunity and cannot be packed into a container.'),
            ]);
        }
    }

    /**
     * An allocated or prepared opportunity asset is still committed to its
     * line and cannot be independently packed into a container. Repack-on-return
     * deliberately reaches executePack only after AssetReturned has moved the
     * assignment to CheckedIn, so physical repacking remains supported.
     *
     * @throws ValidationException
     */
    protected function guardPreDispatchAssignment(StockLevel $stockLevel): void
    {
        $assignment = OpportunityItemAsset::query()
            ->where('stock_level_id', $stockLevel->id)
            ->whereIn('status', [
                AssetAssignmentStatus::Allocated,
                AssetAssignmentStatus::Prepared,
            ])
            ->first();

        if ($assignment === null) {
            return;
        }

        throw ValidationException::withMessages([
            'serialised_item_id' => __('This item is already allocated to an opportunity and cannot be packed into a container.'),
        ]);
    }

    protected function alreadyInContainerConflict(Container $target, ContainerItem $existing): ContainerPackConflictException
    {
        $current = $existing->container;

        return new ContainerPackConflictException(
            conflict: 'already_in_container',
            details: [
                'current_container_id' => $current?->uuid,
                'current_container_name' => $current?->name,
                'current_container_status' => $current?->status->value,
                'associated_opportunity_id' => $current?->opportunity_id,
            ],
            resolutions: [
                'transfer' => [
                    'available' => Gate::check('transfer', $target),
                    'requires_permission' => 'containers.transfer',
                    'description' => __('Move item from current container to this container'),
                ],
            ],
        );
    }

    protected function checkedOutConflict(Container $target, OpportunityItemAsset $assignment): ContainerPackConflictException
    {
        $opportunity = $assignment->item?->opportunity;
        $endsAt = $opportunity?->ends_at;
        $isOverdue = $endsAt !== null && $endsAt->isPast();

        return new ContainerPackConflictException(
            conflict: 'checked_out',
            details: [
                'opportunity_id' => $opportunity?->id,
                'opportunity_subject' => $opportunity?->subject,
                'opportunity_ends_at' => $endsAt?->toIso8601String(),
                'dispatched_at' => $assignment->dispatched_at?->toIso8601String(),
                'is_overdue' => $isOverdue,
            ],
            resolutions: [
                'auto_return_and_pack' => [
                    'available' => Gate::check('packOverride', $target),
                    'requires_permission' => 'containers.pack_override',
                    'description' => __('Return item from opportunity and pack into container'),
                ],
            ],
        );
    }

    protected function guardNestingConfirmation(Container $container, StockLevel $stockLevel): void
    {
        $nested = Container::query()
            ->active()
            ->where('serialised_item_id', $stockLevel->id)
            ->first();

        if ($nested === null) {
            return;
        }

        $depth = app(ContainerManifestService::class)->nestingDepth($container, $nested);
        $itemCount = app(ContainerManifestService::class)->recursiveItemCount($nested);

        throw new ContainerPackConflictException(
            conflict: 'confirm_nesting',
            details: [
                'nested_container_id' => $nested->uuid,
                'nested_container_name' => $nested->name,
                'recursive_item_count' => $itemCount,
                'resulting_depth' => $depth,
            ],
            resolutions: [
                'confirm_nesting' => [
                    'available' => Gate::check('nest', $container),
                    'requires_permission' => 'containers.nest',
                    'description' => __('Confirm packing this container inside the target container'),
                ],
            ],
        );
    }

    /**
     * @throws ValidationException
     */
    protected function guardNestingDepthExceeded(Container $container, StockLevel $stockLevel): void
    {
        $nested = Container::query()
            ->active()
            ->where('serialised_item_id', $stockLevel->id)
            ->first();

        if ($nested === null) {
            return;
        }

        $root = $this->outermostContainer($container);
        $maxDepth = max(1, (int) (Product::query()
            ->whereKey($root->product_id)
            ->value('container_max_nesting_depth') ?? config('containers.default_max_nesting_depth', 2)));

        $currentDepth = $this->depthOf($container);

        if ($currentDepth + 1 > $maxDepth) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Nesting this container would exceed the maximum nesting depth.'),
            ]);
        }
    }

    protected function findCheckedOutAssignment(StockLevel $stockLevel): ?OpportunityItemAsset
    {
        return OpportunityItemAsset::query()
            ->where('stock_level_id', $stockLevel->id)
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched,
                AssetAssignmentStatus::OnHire,
            ])
            ->with('item.opportunity')
            ->first();
    }

    protected function outermostContainer(Container $container): Container
    {
        $root = $container;
        $guard = 0;

        while ($root->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($root->parent_container_id);

            if ($parent === null) {
                break;
            }

            $root = $parent;
            $guard++;
        }

        return $root;
    }

    protected function depthOf(Container $container): int
    {
        $depth = 1;
        $current = $container;
        $guard = 0;

        while ($current->parent_container_id !== null && $guard < 10) {
            $parent = Container::query()->find($current->parent_container_id);

            if ($parent === null) {
                break;
            }

            $depth++;
            $current = $parent;
            $guard++;
        }

        return $depth;
    }

    protected function buildResult(Container $container, ContainerItem $item): PackContainerItemResultData
    {
        $fresh = $item->fresh() ?? $item;
        $notice = app(ContainerSubHireNotice::class)->forStockLevel(
            StockLevel::query()->findOrFail($fresh->serialised_item_id),
        );

        return new PackContainerItemResultData(
            container_item: ContainerItemData::fromModel($fresh),
            notices: $notice !== null ? [$notice] : [],
        );
    }
}

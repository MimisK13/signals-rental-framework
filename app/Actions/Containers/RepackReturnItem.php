<?php

namespace App\Actions\Containers;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerItemData;
use App\Data\Containers\RepackReturnItemData;
use App\Data\Containers\RepackReturnItemResultData;
use App\Enums\AssetAssignmentStatus;
use App\Models\Container;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Containers\ContainerManifestService;
use App\Services\Containers\ContainerSubHireNotice;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\AssetReturned;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Facades\Verbs;

/**
 * One scan during repack-on-return: return the item from the opportunity and,
 * when eligible, pack it into the rebuilt container (serialised-containers.md
 * §"Repack on Return").
 */
class RepackReturnItem
{
    use CommitsVerbsEvents, PromotesOpportunityStatus, RebuildsAvailabilitySnapshots;

    public function __invoke(Container $container, RepackReturnItemData $data): RepackReturnItemResultData
    {
        Gate::authorize('return', $container);
        Gate::authorize('pack', $container);

        $this->guardContainer($container);

        $stockLevel = StockLevel::query()->findOrFail($data->serialised_item_id);
        $assignment = $this->findCheckedOutAssignment($stockLevel, $data->opportunity_id);

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item is not checked out on this opportunity.'),
            ]);
        }

        $shouldPack = $this->shouldPackIntoContainer($container, $stockLevel, $data->free_scan);
        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();
        $overlay = [];
        $containerItem = null;

        try {
            DB::transaction(function () use (
                $container,
                $data,
                $stockLevel,
                $assignment,
                $returnedAt,
                $shouldPack,
                &$overlay,
                &$containerItem,
            ): void {
                app(AutoPromotionContext::class)->suppress(function () use (
                    $assignment,
                    $data,
                    $returnedAt,
                    &$overlay,
                ): void {
                    AssetReturned::fire(
                        state_id: $assignment->state_id,
                        received_by: $data->received_by ?? Auth::id(),
                        return_store_id: $data->return_store_id,
                        returned_at: $returnedAt,
                    );

                    $overlay[$assignment->id] = AssetAssignmentStatus::CheckedIn;
                });

                $this->promoteOpportunityFromItems(
                    $assignment->item?->opportunity,
                    ['assignment_statuses' => $overlay],
                );

                Verbs::commit();

                if ($shouldPack) {
                    $containerItem = (new PackContainerItem)->executePack(
                        $container,
                        $stockLevel,
                        $data->position,
                        $data->notes,
                        returnedFromOpportunityId: $data->opportunity_id,
                    );
                }

                app(ContainerEventRecorder::class)->repackItem(
                    $container->fresh() ?? $container,
                    $stockLevel->id,
                    $data->opportunity_id,
                );
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        if ($assignment->item !== null) {
            $this->rebuildSnapshotsForItems([$assignment->item]);
        }

        $notices = [];

        if ($containerItem !== null) {
            $notice = app(ContainerSubHireNotice::class)->forStockLevel($stockLevel);
            $notices = $notice !== null ? [$notice] : [];
        }

        return new RepackReturnItemResultData(
            opportunity_id: $data->opportunity_id,
            serialised_item_id: $stockLevel->id,
            packed: $containerItem !== null,
            container_item: $containerItem !== null
                ? ContainerItemData::fromModel($containerItem->fresh() ?? $containerItem)
                : null,
            notices: $notices,
        );
    }

    protected function guardContainer(Container $container): void
    {
        if ($container->previous_container_id === null) {
            throw ValidationException::withMessages([
                'container' => __('This container was not created via repack-on-return.'),
            ]);
        }

        if (! $container->status->acceptsPacking()) {
            throw ValidationException::withMessages([
                'container' => __('Items can only be repacked into an open container.'),
            ]);
        }
    }

    protected function findCheckedOutAssignment(StockLevel $stockLevel, int $opportunityId): ?OpportunityItemAsset
    {
        return OpportunityItemAsset::query()
            ->where('stock_level_id', $stockLevel->id)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunityId))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->with('item.opportunity')
            ->first();
    }

    protected function shouldPackIntoContainer(Container $container, StockLevel $stockLevel, bool $freeScan): bool
    {
        if ($freeScan) {
            return true;
        }

        $predecessor = Container::query()->find($container->previous_container_id);

        if ($predecessor === null) {
            return false;
        }

        $originalIds = app(ContainerManifestService::class)
            ->dissolvedTreeSerialisedItemIds($predecessor);

        return in_array($stockLevel->id, $originalIds, true);
    }
}

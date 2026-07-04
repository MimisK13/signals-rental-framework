<?php

namespace App\Services\Containers;

use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\OpportunityItemAsset;
use Illuminate\Support\Carbon;

/**
 * Completes individual-mode kit returns once every asset in the dispatched kit
 * tree has been checked in via {@see ReturnAsset}.
 */
class KitContainerReturnCompletion
{
    public function __construct(
        private readonly ContainerManifestService $manifests,
    ) {}

    /**
     * Resolve the dispatched kit container an assignment belongs to, if any.
     */
    public function containerForAssignment(OpportunityItemAsset $assignment): ?Container
    {
        $stockLevelId = (int) $assignment->stock_level_id;

        $housing = Container::query()
            ->where('serialised_item_id', $stockLevelId)
            ->where('status', ContainerStatus::Dispatched->value)
            ->first();

        if ($housing !== null) {
            return $housing;
        }

        if ($assignment->container_stock_level_id === null) {
            return null;
        }

        return Container::query()
            ->where('serialised_item_id', (int) $assignment->container_stock_level_id)
            ->where('status', ContainerStatus::Dispatched->value)
            ->first();
    }

    /**
     * When every asset in an individual-mode kit has been returned, mark the
     * container returned and emit {@see container.returned}.
     */
    public function maybeCompleteIndividualReturn(Container $container): bool
    {
        if ($container->status !== ContainerStatus::Dispatched) {
            return false;
        }

        $checkinMode = $container->product->container_checkin_mode ?? ContainerCheckinMode::Parent;

        if ($checkinMode !== ContainerCheckinMode::Individual) {
            return false;
        }

        if ($container->opportunity_id === null) {
            return false;
        }

        if (! $this->allKitAssetsReturned($container)) {
            return false;
        }

        $returnedAt = Carbon::now('UTC');

        $container->forceFill([
            'status' => ContainerStatus::Returned->value,
            'returned_at' => $returnedAt,
            'checkin_verification_required_at' => null,
        ])->saveQuietly();

        app(ContainerEventRecorder::class)->returned(
            $container->fresh() ?? $container,
            $this->manifests->recursiveItemCount($container),
        );

        return true;
    }

    public function allKitAssetsReturned(Container $container): bool
    {
        if ($container->opportunity_id === null) {
            return false;
        }

        $stockLevelIds = $this->manifests->kitStockLevelIds($container);

        if ($stockLevelIds === []) {
            return false;
        }

        $outstanding = OpportunityItemAsset::query()
            ->whereIn('stock_level_id', $stockLevelIds)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $container->opportunity_id))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->count();

        return $outstanding === 0;
    }
}

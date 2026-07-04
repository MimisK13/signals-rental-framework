<?php

namespace App\Actions\Containers;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerData;
use App\Data\Containers\KitContainerReturnResultData;
use App\Data\Containers\ReturnKitContainerData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\OpportunityItemAsset;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Containers\ContainerManifestService;
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
 * Returns a dispatched kit/hybrid container according to the product's
 * {@see ContainerCheckinMode} (serialised-containers.md §"Kit Returns").
 *
 * Parent and parent-then-verify modes return every asset in one scan. Individual
 * mode rejects whole-kit return — use {@see ReturnAsset} per component instead.
 *
 * Permissions: {@see opportunities.edit} via the container policy's `return` ability
 * (mirrors {@see ReturnAsset}).
 */
class ReturnKitContainer
{
    use CommitsVerbsEvents, PromotesOpportunityStatus, RebuildsAvailabilitySnapshots;

    public function __invoke(Container $container, ReturnKitContainerData $data): KitContainerReturnResultData
    {
        Gate::authorize('return', $container);

        $this->guardContainer($container);

        $checkinMode = $container->product->container_checkin_mode ?? ContainerCheckinMode::Parent;

        if ($checkinMode === ContainerCheckinMode::Individual) {
            throw ValidationException::withMessages([
                'container' => __('Individual check-in mode requires each component to be scanned separately.'),
            ]);
        }

        $opportunityId = (int) $container->opportunity_id;
        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();
        $stockLevelIds = app(ContainerManifestService::class)->kitStockLevelIds($container);

        $assignments = OpportunityItemAsset::query()
            ->whereIn('stock_level_id', $stockLevelIds)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunityId))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->with('item.opportunity')
            ->get();

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'container' => __('No dispatched assets remain on this kit to return.'),
            ]);
        }

        $returnedIds = [];
        $overlay = [];

        try {
            DB::transaction(function () use (
                $container,
                $assignments,
                $data,
                $returnedAt,
                $checkinMode,
                &$returnedIds,
                &$overlay,
            ): void {
                app(AutoPromotionContext::class)->suppress(function () use (
                    $assignments,
                    $data,
                    $returnedAt,
                    &$returnedIds,
                    &$overlay,
                ): void {
                    foreach ($assignments as $assignment) {
                        AssetReturned::fire(
                            state_id: $assignment->state_id,
                            received_by: $data->received_by ?? Auth::id(),
                            return_store_id: $data->return_store_id,
                            returned_at: $returnedAt,
                        );

                        $returnedIds[] = $assignment->id;
                        $overlay[$assignment->id] = AssetAssignmentStatus::CheckedIn;
                    }
                });

                $this->promoteOpportunityFromItems(
                    $assignments->first()->item->opportunity,
                    ['assignment_statuses' => $overlay],
                );

                Verbs::commit();

                $returnedAtCarbon = Carbon::parse($returnedAt);
                $verificationRequired = $checkinMode === ContainerCheckinMode::ParentThenVerify;

                $container->forceFill([
                    'status' => ContainerStatus::Returned->value,
                    'returned_at' => $returnedAtCarbon,
                    'checkin_verification_required_at' => $verificationRequired ? $returnedAtCarbon : null,
                ])->saveQuietly();

                app(ContainerEventRecorder::class)->returned(
                    $container->fresh() ?? $container,
                    count($returnedIds),
                );
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        $this->rebuildSnapshotsForItems(
            $assignments->load('item')->pluck('item')->filter()->all(),
        );

        $fresh = $container->fresh() ?? $container;

        return new KitContainerReturnResultData(
            container: ContainerData::fromModel($fresh),
            opportunity_id: $opportunityId,
            assets_returned: count($returnedIds),
            assets_returned_ids: $returnedIds,
            verification_required: $fresh->checkin_verification_required_at !== null,
        );
    }

    protected function guardContainer(Container $container): void
    {
        if ($container->status !== ContainerStatus::Dispatched) {
            throw ValidationException::withMessages([
                'container' => __('Only dispatched kits can be returned.'),
            ]);
        }

        if (! $container->availabilityMode()->isBookableKit()) {
            throw ValidationException::withMessages([
                'container' => __('Only kit or hybrid containers support kit return.'),
            ]);
        }

        if ($container->opportunity_id === null) {
            throw ValidationException::withMessages([
                'container' => __('This kit is not linked to an opportunity.'),
            ]);
        }
    }
}

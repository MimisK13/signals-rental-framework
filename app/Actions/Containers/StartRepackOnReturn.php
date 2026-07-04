<?php

namespace App\Actions\Containers;

use App\Actions\Opportunities\Concerns\RebuildsAvailabilitySnapshots;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerData;
use App\Data\Containers\RepackOnReturnStartedResultData;
use App\Data\Containers\StartRepackOnReturnData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItemAsset;
use App\Models\StockLevel;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\AssetReturned;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Facades\Verbs;

/**
 * Initiates repack-on-return for a transport container housing during check-in
 * (serialised-containers.md §"Repack on Return").
 *
 * Returns the housing from the opportunity, creates a new open container linked to
 * the most recently dissolved predecessor, and emits {@code container.repack.started}.
 */
class StartRepackOnReturn
{
    use CommitsVerbsEvents, PromotesOpportunityStatus, RebuildsAvailabilitySnapshots;

    public function __invoke(StartRepackOnReturnData $data): RepackOnReturnStartedResultData
    {
        Gate::authorize('create', Container::class);
        Gate::authorize('opportunities.edit');

        $opportunity = Opportunity::query()->whereKey($data->opportunity_id)->firstOrFail();
        $housing = StockLevel::query()->with('product')->findOrFail($data->serialised_item_id);

        $this->guard($housing);

        $assignment = $this->findCheckedOutAssignment($housing, $opportunity->id);

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The housing item is not checked out on this opportunity.'),
            ]);
        }

        $predecessor = $this->findMostRecentDissolvedContainer($housing);
        $returnedAt = $data->returned_at ?? Carbon::now('UTC')->toIso8601String();
        $returnedId = $assignment->id;
        $overlay = [];
        $container = null;

        try {
            DB::transaction(function () use (
                $data,
                $housing,
                $assignment,
                $predecessor,
                $returnedAt,
                $opportunity,
                &$overlay,
                &$container,
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

                $scanMode = $this->resolveScanMode($housing, $predecessor);

                try {
                    $container = Container::create([
                        'name' => $this->defaultName($housing),
                        'serialised_item_id' => $housing->id,
                        'product_id' => $housing->product_id,
                        'previous_container_id' => $predecessor?->id,
                        'is_temporary' => false,
                        'barcode' => $this->defaultBarcode($housing),
                        'store_id' => $housing->store_id,
                        'scan_mode' => $scanMode,
                        'status' => ContainerStatus::Open,
                    ]);
                } catch (QueryException) {
                    throw ValidationException::withMessages([
                        'serialised_item_id' => __('This serialised item already has an active container.'),
                    ]);
                }

                app(ContainerEventRecorder::class)->created($container);
                app(ContainerEventRecorder::class)->repackStarted(
                    $container,
                    $predecessor?->id,
                    $opportunity->id,
                );
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        if ($assignment->item !== null) {
            $this->rebuildSnapshotsForItems([$assignment->item]);
        }

        $fresh = $container?->fresh() ?? $container;

        return new RepackOnReturnStartedResultData(
            container: ContainerData::fromModel($fresh),
            opportunity_id: $opportunity->id,
            previous_container_id: $predecessor?->id,
            housing_asset_returned_id: $returnedId,
        );
    }

    protected function guard(StockLevel $housing): void
    {
        if (! $housing->isSerialised()) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Only serialised items can be used as container housing.'),
            ]);
        }

        $product = $housing->product;

        if (! $product?->is_containerable) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('The item\'s product is not containerable.'),
            ]);
        }

        if ($product->container_availability_mode !== ContainerAvailabilityMode::Transport) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Repack on return is only supported for transport-mode containers.'),
            ]);
        }

        if (! $product->container_repack_on_return) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('Repack on return is not enabled for this container product.'),
            ]);
        }

        $active = Container::query()
            ->active()
            ->where('serialised_item_id', $housing->id)
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'serialised_item_id' => __('This serialised item already has an active container.'),
            ]);
        }
    }

    protected function findCheckedOutAssignment(StockLevel $housing, int $opportunityId): ?OpportunityItemAsset
    {
        return OpportunityItemAsset::query()
            ->where('stock_level_id', $housing->id)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunityId))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->with('item.opportunity')
            ->first();
    }

    protected function findMostRecentDissolvedContainer(StockLevel $housing): ?Container
    {
        return Container::query()
            ->where('serialised_item_id', $housing->id)
            ->where('status', ContainerStatus::Dissolved->value)
            ->orderByDesc('dissolved_at')
            ->first();
    }

    protected function resolveScanMode(StockLevel $housing, ?Container $predecessor): ContainerScanMode
    {
        $template = $housing->product->container_template ?? ['slots' => []];
        $hasTemplate = ($template['slots'] ?? []) !== [];

        if ($predecessor !== null && $hasTemplate) {
            return $predecessor->scan_mode;
        }

        return ContainerScanMode::Strict;
    }

    protected function defaultName(StockLevel $housing): string
    {
        $product = $housing->product;
        $base = $housing->item_name ?: ($product->name ?? 'Container');

        if ($housing->asset_number !== null && $housing->asset_number !== '') {
            return trim($base.' #'.$housing->asset_number);
        }

        return $base;
    }

    protected function defaultBarcode(StockLevel $housing): ?string
    {
        return $housing->barcode
            ?? $housing->asset_number
            ?? $housing->serial_number;
    }
}

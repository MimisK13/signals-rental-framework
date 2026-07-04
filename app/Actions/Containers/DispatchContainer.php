<?php

namespace App\Actions\Containers;

use App\Actions\Containers\Concerns\DispatchesOpportunityContainerAssets;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerData;
use App\Data\Containers\ContainerDispatchProductVerdictData;
use App\Data\Containers\ContainerDispatchResultData;
use App\Data\Containers\DispatchContainerData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerDissolveReason;
use App\Enums\ContainerStatus;
use App\Enums\OpportunityState;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Models\Container;
use App\Models\Opportunity;
use App\Services\Containers\ContainerDispatchValidator;
use App\Services\Containers\ContainerDissolveService;
use App\Services\Containers\ContainerEventRecorder;
use App\Services\Containers\ContainerManifestService;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\Concerns\PromotesOpportunityStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;
use Thunk\Verbs\Facades\Verbs;

/**
 * Container dispatch orchestrator — routes by availability mode:
 *
 *  - **Transport** — dissolve-on-dispatch (serialised-containers.md §"Dissolve-on-Dispatch")
 *  - **Kit / Hybrid** — persistent unit dispatch via {@see DispatchKitContainer}
 *
 * Permissions: {@see opportunities.edit} + {@see containers.view} via the container
 * policy's `dispatch` ability.
 */
class DispatchContainer
{
    use CommitsVerbsEvents, DispatchesOpportunityContainerAssets, PromotesOpportunityStatus;

    public function __invoke(Container $container, DispatchContainerData $data): ContainerDispatchResultData
    {
        Gate::authorize('dispatch', $container);

        if ($container->availabilityMode()->isBookableKit()) {
            return (new DispatchKitContainer)($container, $data);
        }

        return $this->dispatchTransport($container, $data);
    }

    protected function dispatchTransport(Container $container, DispatchContainerData $data): ContainerDispatchResultData
    {
        $this->pendingLinesByProduct = [];

        $opportunity = Opportunity::query()->whereKey($data->opportunity_id)->firstOrFail();

        $this->guardTransportContainer($container);
        $this->guardOpportunity($opportunity);

        $approvedAmendments = $data->amendments !== null
            ? array_values($data->amendments->all())
            : [];
        $validation = app(ContainerDispatchValidator::class)->validate(
            container: $container,
            opportunity: $opportunity,
            actor: Auth::user(),
            autoAddMissing: $data->auto_add_missing,
            approvedAmendments: $approvedAmendments,
        );

        if ($validation['has_failures']) {
            throw ValidationException::withMessages([
                'dispatch' => [__('Container dispatch failed validation.')],
                'validation' => [array_map(
                    fn (ContainerDispatchProductVerdictData $row): array => $row->toArray(),
                    $validation['products'],
                )],
            ]);
        }

        if ($validation['has_prompts']) {
            throw new ContainerDispatchAmendmentRequiredException(
                amendments: array_map(
                    fn (ContainerDispatchProductVerdictData $row): array => $this->promptPayload($row),
                    $validation['prompts'],
                ),
                validation: array_map(
                    fn (ContainerDispatchProductVerdictData $row): array => $row->toArray(),
                    $validation['products'],
                ),
            );
        }

        $autoAdded = [];
        $amended = [];
        $dispatchedAssetIds = [];
        $containersDissolved = 0;

        $containersInTree = count(app(ContainerManifestService::class)->containersInTree($container));

        try {
            DB::transaction(function () use (
                $container,
                $opportunity,
                $validation,
                $approvedAmendments,
                $containersInTree,
                &$autoAdded,
                &$amended,
                &$dispatchedAssetIds,
                &$containersDissolved,
            ): void {
                $opportunity->refresh()->load('items');

                $this->applyAmendments(
                    opportunity: $opportunity,
                    validation: $validation,
                    approvedAmendments: $approvedAmendments,
                    autoAdded: $autoAdded,
                    amended: $amended,
                );

                if ($autoAdded !== [] || $amended !== []) {
                    Verbs::commit();
                    $opportunity->refresh()->load('items');
                    $this->pendingLinesByProduct = [];
                } else {
                    $opportunity->refresh()->load('items');
                }

                $dispatchedAt = Carbon::now('UTC')->toIso8601String();
                $assets = app(ContainerManifestService::class)->dispatchAssets($container);
                $overlay = [];

                app(AutoPromotionContext::class)->suppress(function () use (
                    $opportunity,
                    $assets,
                    $dispatchedAt,
                    &$dispatchedAssetIds,
                    &$overlay,
                ): void {
                    $overlay = $this->dispatchAssetManifest($opportunity, $assets, $dispatchedAt);
                    $dispatchedAssetIds = array_keys($overlay);
                });

                $this->promoteOpportunityFromItems(
                    $opportunity->fresh(),
                    ['assignment_statuses' => $overlay],
                );

                Verbs::commit();

                $itemCount = app(ContainerDissolveService::class)->dissolveTree(
                    $container,
                    ContainerDissolveReason::Dispatched,
                    $opportunity->id,
                );

                app(ContainerEventRecorder::class)->dissolved(
                    $container->fresh() ?? $container,
                    ContainerDissolveReason::Dispatched,
                    $itemCount,
                );

                $containersDissolved = $containersInTree;
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        return new ContainerDispatchResultData(
            container: ContainerData::fromModel($container->fresh() ?? $container),
            opportunity_id: $opportunity->id,
            assets_checked_out: count($dispatchedAssetIds),
            assets_dispatched: $dispatchedAssetIds,
            auto_added_products: $autoAdded,
            amended_products: $amended,
            containers_dissolved: $containersDissolved,
        );
    }

    protected function guardTransportContainer(Container $container): void
    {
        if ($container->status !== ContainerStatus::Sealed) {
            throw ValidationException::withMessages([
                'container' => __('Only sealed containers can be dispatched.'),
            ]);
        }

        if ($container->availabilityMode() !== ContainerAvailabilityMode::Transport) {
            throw ValidationException::withMessages([
                'container' => __('Only transport-mode containers dissolve on dispatch.'),
            ]);
        }
    }

    protected function guardOpportunity(Opportunity $opportunity): void
    {
        if ($opportunity->state !== OpportunityState::Order) {
            throw ValidationException::withMessages([
                'opportunity' => __('The target opportunity must be an order.'),
            ]);
        }

        if ($opportunity->statusEnum()->isClosed()) {
            throw ValidationException::withMessages([
                'opportunity' => __('A closed opportunity cannot receive a container dispatch.'),
            ]);
        }
    }
}

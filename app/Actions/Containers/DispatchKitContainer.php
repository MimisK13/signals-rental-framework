<?php

namespace App\Actions\Containers;

use App\Actions\Containers\Concerns\DispatchesOpportunityContainerAssets;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Containers\ContainerData;
use App\Data\Containers\ContainerDispatchProductVerdictData;
use App\Data\Containers\ContainerDispatchResultData;
use App\Data\Containers\DispatchContainerData;
use App\Enums\ContainerStatus;
use App\Enums\OpportunityState;
use App\Events\Containers\BeforeContainerDispatched;
use App\Events\Containers\ContainerDispatchedLifecycle;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Models\Container;
use App\Models\Opportunity;
use App\Services\Containers\ContainerDispatchValidator;
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
 * Kit/hybrid persistent dispatch (serialised-containers.md §"Kit Dispatch").
 *
 * Checks out the housing asset and all contained assets as a unit, transitions
 * the container to `dispatched`, and retains container demands + memberships.
 * Hybrid pool-binding contents allocate to their own opportunity lines; fixed
 * contents and the housing asset allocate to the kit product line.
 */
class DispatchKitContainer
{
    use CommitsVerbsEvents, DispatchesOpportunityContainerAssets, PromotesOpportunityStatus;

    public function __invoke(Container $container, DispatchContainerData $data): ContainerDispatchResultData
    {
        Gate::authorize('dispatch', $container);

        $this->pendingLinesByProduct = [];

        $opportunity = Opportunity::query()->whereKey($data->opportunity_id)->firstOrFail();

        $this->guardContainer($container);
        $this->guardOpportunity($opportunity);

        $approvedAmendments = $data->amendments !== null
            ? array_values($data->amendments->all())
            : [];
        $validation = app(ContainerDispatchValidator::class)->validateKit(
            container: $container,
            opportunity: $opportunity,
            actor: Auth::user(),
            autoAddMissing: $data->auto_add_missing,
            approvedAmendments: $approvedAmendments,
        );

        if ($validation['has_failures']) {
            throw ValidationException::withMessages([
                'dispatch' => [__('Kit dispatch failed validation.')],
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

        BeforeContainerDispatched::dispatch($container, $opportunity);

        try {
            DB::transaction(function () use (
                $container,
                $opportunity,
                $validation,
                $approvedAmendments,
                &$autoAdded,
                &$amended,
                &$dispatchedAssetIds,
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

                $contentAutoAdded = $this->ensureKitContentLines($opportunity, $container);

                if ($contentAutoAdded !== []) {
                    Verbs::commit();
                    $opportunity->refresh()->load('items');
                    $this->pendingLinesByProduct = [];
                    $autoAdded = array_merge($autoAdded, $contentAutoAdded);
                }

                $dispatchedAt = Carbon::now('UTC')->toIso8601String();
                $assets = app(ContainerManifestService::class)->kitDispatchAssets($container);
                $overlay = [];

                app(AutoPromotionContext::class)->suppress(function () use (
                    $opportunity,
                    $assets,
                    $dispatchedAt,
                    &$dispatchedAssetIds,
                    &$overlay,
                ): void {
                    $overlay = $this->dispatchAssetManifest(
                        $opportunity,
                        $assets,
                        $dispatchedAt,
                        ignoreAvailability: true,
                    );
                    $dispatchedAssetIds = array_keys($overlay);
                });

                Verbs::commit();

                $this->promoteOpportunityFromItems(
                    $opportunity->fresh(['items.assets']),
                );

                Verbs::commit();

                $dispatchedAtCarbon = Carbon::parse($dispatchedAt);
                $container->forceFill([
                    'status' => ContainerStatus::Dispatched->value,
                    'dispatched_at' => $dispatchedAtCarbon,
                    'opportunity_id' => $opportunity->id,
                ])->saveQuietly();

                app(ContainerEventRecorder::class)->dispatched(
                    $container->fresh() ?? $container,
                    count($assets),
                );
            });
        } catch (Throwable $e) {
            $this->resetVerbsState();

            throw $e;
        }

        $fresh = $container->fresh() ?? $container;
        ContainerDispatchedLifecycle::dispatch($fresh, $opportunity, count($dispatchedAssetIds));

        return new ContainerDispatchResultData(
            container: ContainerData::fromModel($fresh),
            opportunity_id: $opportunity->id,
            assets_checked_out: count($dispatchedAssetIds),
            assets_dispatched: $dispatchedAssetIds,
            auto_added_products: $autoAdded,
            amended_products: $amended,
            containers_dissolved: 0,
        );
    }

    protected function guardContainer(Container $container): void
    {
        if ($container->status === ContainerStatus::Dispatched) {
            throw ValidationException::withMessages([
                'container' => __('This kit is already dispatched.'),
            ]);
        }

        if ($container->status !== ContainerStatus::Sealed) {
            throw ValidationException::withMessages([
                'container' => __('Only sealed containers can be dispatched.'),
            ]);
        }

        if (! $container->availabilityMode()->isBookableKit()) {
            throw ValidationException::withMessages([
                'container' => __('Only kit or hybrid containers persist on dispatch.'),
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

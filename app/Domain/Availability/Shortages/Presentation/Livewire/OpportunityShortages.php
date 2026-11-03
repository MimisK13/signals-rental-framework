<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Presentation\Livewire;

use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\DemandReadContext;
use App\Domain\Availability\Services\StockReadContext;
use App\Domain\Availability\Shortages\Actions\AcknowledgeOpportunityShortages;
use App\Domain\Availability\Shortages\Actions\ApplyBatchShortageResolution;
use App\Domain\Availability\Shortages\Actions\ApplyShortageResolution;
use App\Domain\Availability\Shortages\Actions\CancelShortageResolution;
use App\Domain\Availability\Shortages\Actions\ConfirmShortageResolution;
use App\Domain\Availability\Shortages\Actions\FailShortageResolution;
use App\Domain\Availability\Shortages\Actions\FulfillShortageResolution;
use App\Domain\Availability\Shortages\Actions\StartShortageResolution;
use App\Domain\Availability\Shortages\Contracts\StockContextAwareShortageResolver;
use App\Domain\Availability\Shortages\Data\AcknowledgeShortageData;
use App\Domain\Availability\Shortages\Data\ApplyBatchResolutionData;
use App\Domain\Availability\Shortages\Data\ApplyResolutionData;
use App\Domain\Availability\Shortages\Data\TransitionShortageResolutionData;
use App\Domain\Availability\Shortages\Enums\ShortageDispatchPolicy;
use App\Domain\Availability\Shortages\Enums\ShortageResolutionStatus;
use App\Domain\Availability\Shortages\Models\ShortageResolution;
use App\Domain\Availability\Shortages\Services\ShortageBatchGrouper;
use App\Domain\Availability\Shortages\Services\ShortageConfirmationGate;
use App\Domain\Availability\Shortages\Services\ShortageCrossOpportunityScanner;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\Services\ShortageEventRecorder;
use App\Domain\Availability\Shortages\Services\ShortageResolverRegistry;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;
use App\Livewire\Concerns\HasOpportunityActions;
use App\Models\Opportunity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Opportunity Shortages tab (M8-4c) — the shortage panel + resolver UI
 * (shortage-resolution-sub-hires.md §2, §3, §7, §8).
 *
 * Reads compute shortages live from the availability engine via the
 * {@see ShortageDetector} (shortages are never persisted), enumerate the
 * applicable resolvers per short line through the {@see ShortageResolverRegistry},
 * surface the {@see ShortageConfirmationGate} pre-check (the same Block/Warn/Allow
 * decision the convert-to-order action enforces) and the store dispatch policy, and
 * list + transition the persisted {@see ShortageResolution} records through the
 * §8.3 lifecycle. Every mutation calls the SAME action classes the API controller
 * uses (each authorises internally) — this component never self-HTTPs.
 *
 * Reverb-live: subscribes to `availability.opportunity.{id}` so the panel refreshes
 * when shortages change (Echo client bundle wired in M8-4a).
 */
#[Layout('components.layouts.app')]
final class OpportunityShortages extends Component
{
    use HasOpportunityActions;

    public Opportunity $opportunity;

    /** Whether the actor may apply/transition/acknowledge (vs read-only view). */
    public bool $canResolve = false;

    /** The resolver/option the user is composing in the apply modal. */
    public ?int $applyItemId = null;

    public ?string $applyResolverKey = null;

    public int $applyOptionIndex = 0;

    public ?string $applyNotes = null;

    /** Batch resolution selection across jobs. */
    public ?string $applyBatchResolverKey = null;

    public int $applyBatchOptionIndex = 0;

    /** @var list<int> */
    public array $applyBatchItemIds = [];

    public ?string $applyBatchNotes = null;

    /** The resolution + target status the reason modal is collecting a reason for. */
    public ?int $reasonResolutionId = null;

    public ?string $reasonTransition = null;

    public ?string $transitionReason = null;

    /** Notes captured when acknowledging shortages ahead of conversion. */
    public ?string $acknowledgeNotes = null;

    /** @var array<string, mixed>|null */
    private ?array $renderDataCache = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('shortages.view');

        $this->opportunity = $opportunity->load('store');
        $this->canResolve = Gate::allows('shortages.resolve');
    }

    public function rendering(View $view): void
    {
        $view->title($this->opportunity->subject.' — Shortages');
    }

    /**
     * Live refresh when the opportunity's availability changes (a booking, return,
     * stock movement, or resolution elsewhere). The broadcast is a light signal;
     * the with() computed values re-read the engine on the subsequent render so the
     * shortage list, resolver options, gate decision, and resolutions all reflect
     * the recalculated picture. Mirrors the M8-2 / M8-4b `#[On('echo-private:...')]`
     * convention — dynamic `{opportunity.id}` segment + dot-prefixed broadcast name.
     */
    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        $this->opportunity->refresh();
    }

    /**
     * Open the apply modal for a resolver option on a short line item.
     */
    public function selectResolution(int $itemId, string $resolverKey, int $optionIndex): void
    {
        $this->applyItemId = $itemId;
        $this->applyResolverKey = $resolverKey;
        $this->applyOptionIndex = $optionIndex;
        $this->applyNotes = null;
        $this->dispatch('open-modal', 'apply-resolution');
        $this->js("\$dispatch('open-modal', 'apply-resolution')");
    }

    /**
     * Apply the chosen resolver option to the line item's current shortage. The
     * shortage is recomputed fresh inside the action; on success the panel refreshes
     * and the active-resolutions list picks up the new record.
     */
    public function applyResolution(): void
    {
        if ($this->applyItemId === null || $this->applyResolverKey === null) {
            return;
        }

        try {
            new ApplyShortageResolution(
                app(ShortageDetector::class),
                app(ShortageResolverRegistry::class),
            )(ApplyResolutionData::from([
                'opportunity_item_id' => $this->applyItemId,
                'resolver_key' => $this->applyResolverKey,
                'option_index' => $this->applyOptionIndex,
                'notes' => $this->applyNotes,
            ]));

            session()->flash('shortage_status', 'Resolution applied.');
            $this->resetApplyState();
            $this->dispatch('shortage-modal-close', name: 'apply-resolution');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to resolve shortages.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to apply this resolution.');
        }

        $this->opportunity->refresh();
    }

    /**
     * Open the batch apply modal for a cross-job sub-hire option.
     *
     * @param  list<int>  $itemIds
     */
    public function selectBatchResolution(array $itemIds, string $resolverKey, int $optionIndex): void
    {
        $this->applyBatchItemIds = $itemIds;
        $this->applyBatchResolverKey = $resolverKey;
        $this->applyBatchOptionIndex = $optionIndex;
        $this->applyBatchNotes = null;
        $this->dispatch('open-modal', 'apply-batch-resolution');
        $this->js("\$dispatch('open-modal', 'apply-batch-resolution')");
    }

    public function applyBatchResolution(): void
    {
        if ($this->applyBatchItemIds === [] || $this->applyBatchResolverKey === null) {
            return;
        }

        try {
            new ApplyBatchShortageResolution(
                app(ShortageDetector::class),
                app(ShortageResolverRegistry::class),
                app(ShortageBatchGrouper::class),
            )(ApplyBatchResolutionData::from([
                'opportunity_item_ids' => $this->applyBatchItemIds,
                'resolver_key' => $this->applyBatchResolverKey,
                'option_index' => $this->applyBatchOptionIndex,
                'notes' => $this->applyBatchNotes,
            ]));

            session()->flash('shortage_status', 'Batch resolution applied.');
            $this->resetBatchApplyState();
            $this->dispatch('shortage-modal-close', name: 'apply-batch-resolution');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to resolve shortages.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to apply this batch resolution.');
        }

        $this->opportunity->refresh();
    }

    public function confirmResolution(int $resolutionId): void
    {
        $this->runResolutionTransition($resolutionId, fn (ShortageResolution $resolution) => (new ConfirmShortageResolution(app(ShortageEventRecorder::class)))($resolution));
    }

    public function startResolution(int $resolutionId): void
    {
        $this->runResolutionTransition($resolutionId, fn (ShortageResolution $resolution) => (new StartShortageResolution(app(ShortageEventRecorder::class)))($resolution));
    }

    public function fulfillResolution(int $resolutionId): void
    {
        $this->runResolutionTransition($resolutionId, fn (ShortageResolution $resolution) => (new FulfillShortageResolution(app(ShortageEventRecorder::class)))($resolution));
    }

    /**
     * Open the reason modal for a cancel/fail transition (both carry an optional
     * reason persisted to `cancellation_reason`).
     */
    public function promptReason(int $resolutionId, string $transition): void
    {
        $this->reasonResolutionId = $resolutionId;
        $this->reasonTransition = $transition;
        $this->transitionReason = null;
    }

    /**
     * Apply the reason-carrying cancel/fail transition collected in the modal.
     */
    public function submitReasonTransition(): void
    {
        if ($this->reasonResolutionId === null || $this->reasonTransition === null) {
            return;
        }

        $reason = $this->transitionReason;
        $transition = $this->reasonTransition;

        $this->runResolutionTransition($this->reasonResolutionId, function (ShortageResolution $resolution) use ($transition, $reason) {
            $payload = TransitionShortageResolutionData::from(['reason' => $reason]);

            return $transition === 'fail'
                ? (new FailShortageResolution(app(ShortageEventRecorder::class)))($resolution, $payload)
                : (new CancelShortageResolution(app(ShortageEventRecorder::class)))($resolution, $payload);
        });

        $this->reasonResolutionId = null;
        $this->reasonTransition = null;
        $this->transitionReason = null;
        $this->dispatch('shortage-modal-close', name: 'transition-reason');
    }

    /**
     * Explicitly acknowledge the opportunity's shortages ahead of conversion,
     * recording the gate acknowledgement with a frozen snapshot (§7.3). Surfaced
     * when the gate decision is Warn (acknowledgement required) — it lets the user
     * record awareness on this tab before clicking Convert to Order on the overview.
     */
    public function acknowledgeShortages(): void
    {
        try {
            (new AcknowledgeOpportunityShortages(app(ShortageConfirmationGate::class)))(
                $this->opportunity,
                AcknowledgeShortageData::from(['notes' => $this->acknowledgeNotes]),
            );

            session()->flash('shortage_status', 'Shortages acknowledged.');
            $this->acknowledgeNotes = null;
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to acknowledge shortages.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'Unable to acknowledge shortages.');
        }

        $this->opportunity->refresh();
    }

    /**
     * Run a resolution status transition, catching the auth/422 failures the action
     * classes raise (an illegal §8.3 move is a 422) and flashing the first message.
     *
     * @param  \Closure(ShortageResolution): mixed  $action
     */
    protected function runResolutionTransition(int $resolutionId, \Closure $action): void
    {
        try {
            $resolution = ShortageResolution::query()
                ->forOpportunity($this->opportunity->id)
                ->findOrFail($resolutionId);

            $action($resolution);
            session()->flash('shortage_status', 'Resolution updated.');
        } catch (AuthorizationException) {
            session()->flash('error', 'You do not have permission to update this resolution.');
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first() ?? 'This transition is not permitted.');
        }

        $this->opportunity->refresh();
    }

    protected function resetApplyState(): void
    {
        $this->applyItemId = null;
        $this->applyResolverKey = null;
        $this->applyOptionIndex = 0;
        $this->applyNotes = null;
    }

    protected function resetBatchApplyState(): void
    {
        $this->applyBatchItemIds = [];
        $this->applyBatchResolverKey = null;
        $this->applyBatchOptionIndex = 0;
        $this->applyBatchNotes = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        if ($this->renderDataCache !== null) {
            return $this->renderDataCache;
        }

        // Shares only raw stock facts across this read-only shortage evaluation.
        // Demand and item-specific exclusions remain independently evaluated.
        $stockContext = new StockReadContext;
        $demandContext = new DemandReadContext;
        $shortages = app(ShortageDetector::class)->forOpportunity($this->opportunity, $stockContext, $demandContext);
        $batchShortages = app(ShortageCrossOpportunityScanner::class)->withPeerShortages($this->opportunity, $shortages, $stockContext, $demandContext);
        $batchGroups = app(ShortageBatchGrouper::class)->groupsForOpportunity($batchShortages, $this->opportunity->id);
        $registry = app(ShortageResolverRegistry::class);

        /** @var list<array<string, mixed>> $rows */
        $rows = $shortages
            ->map(function (Shortage $shortage) use ($registry, $stockContext, $demandContext): array {
                return [
                    'shortage' => $shortage,
                    'resolvers' => array_map(
                        static fn ($resolver): array => [
                            'key' => $resolver->key(),
                            'name' => $resolver->name(),
                            'auto_executable' => $resolver->isAutoExecutable(),
                            'options' => array_map(
                                static fn ($option): array => $option->toArray(),
                                $resolver instanceof StockContextAwareShortageResolver
                                    ? $resolver->getOptionsWithStockContext($shortage, $stockContext, $demandContext)
                                    : $resolver->getOptions($shortage),
                            ),
                        ],
                        $registry->applicableTo($shortage),
                    ),
                ];
            })
            ->all();

        /** @var list<array<string, mixed>> $batchRows */
        $batchRows = array_map(function ($group) use ($registry): array {
            return [
                'group' => $group,
                'resolvers' => array_map(
                    static fn ($resolver): array => [
                        'key' => $resolver->key(),
                        'name' => $resolver->name(),
                        'options' => array_map(
                            static fn ($option): array => $option->toArray(),
                            $resolver->getBatchOptions($group),
                        ),
                    ],
                    $registry->batchApplicableTo($group),
                ),
            ];
        }, $batchGroups);

        return $this->renderDataCache = [
            ...$this->opportunityActionData(),
            'shortageRows' => $rows,
            'batchRows' => $batchRows,
            'gate' => app(ShortageConfirmationGate::class)->evaluate($this->opportunity),
            'dispatchPolicy' => $this->opportunity->store?->dispatchPolicy()
                ?? ShortageDispatchPolicy::default(),
            'resolutions' => $this->loadResolutions(),
            'appliedOption' => $this->appliedOption($rows),
            'appliedBatchOption' => $this->appliedBatchOption($batchRows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $batchRows
     * @return array<string, mixed>|null
     */
    protected function appliedBatchOption(array $batchRows): ?array
    {
        if ($this->applyBatchItemIds === [] || $this->applyBatchResolverKey === null) {
            return null;
        }

        sort($this->applyBatchItemIds);

        foreach ($batchRows as $row) {
            $groupIds = $row['group']->opportunityItemIds();
            sort($groupIds);

            if ($groupIds !== $this->applyBatchItemIds) {
                continue;
            }

            foreach ($row['resolvers'] as $resolver) {
                if ($resolver['key'] !== $this->applyBatchResolverKey) {
                    continue;
                }

                return $resolver['options'][$this->applyBatchOptionIndex] ?? null;
            }
        }

        return null;
    }

    /**
     * The opportunity's persisted resolutions, newest first, each annotated with the
     * legal §8.3 transitions for the action buttons.
     *
     * @return Collection<int, ShortageResolution>
     */
    protected function loadResolutions(): Collection
    {
        return ShortageResolution::query()
            ->forOpportunity($this->opportunity->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The option currently selected in the apply modal (for the confirm summary), or
     * null when the modal is closed / the selection is stale.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    protected function appliedOption(array $rows): ?array
    {
        if ($this->applyItemId === null || $this->applyResolverKey === null) {
            return null;
        }

        foreach ($rows as $row) {
            /** @var Shortage $shortage */
            $shortage = $row['shortage'];

            if ($shortage->opportunityItemId !== $this->applyItemId) {
                continue;
            }

            foreach ($row['resolvers'] as $resolver) {
                if ($resolver['key'] !== $this->applyResolverKey) {
                    continue;
                }

                return $resolver['options'][$this->applyOptionIndex] ?? null;
            }
        }

        return null;
    }

    /**
     * Whether $resolution may legally transition to $target (§8.3), driving the
     * enabled/disabled state of the lifecycle buttons.
     */
    public function canTransition(ShortageResolution $resolution, string $target): bool
    {
        $targetStatus = ShortageResolutionStatus::tryFrom($target);

        return $targetStatus !== null && $resolution->status->canTransitionTo($targetStatus);
    }

    /**
     * A human window label for a shortage's effective period.
     */
    public function windowLabel(Shortage $shortage): string
    {
        $sentinel = Carbon::parse(Demand::SENTINEL_DATE);
        $start = $shortage->startsAt->format('d M Y');

        if ($shortage->endsAt->equalTo($sentinel)) {
            return $start.' → open-ended';
        }

        return $start.' → '.$shortage->endsAt->format('d M Y');
    }

    public function render(): View
    {
        return view('shortages::opportunity-shortages');
    }
}

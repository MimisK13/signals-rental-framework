<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Verbs\States\OpportunityItemState;
use Illuminate\Database\Eloquent\Collection;
use LogicException;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Lifecycle\StateManager;
use Thunk\Verbs\Models\VerbStateEvent;
use Thunk\Verbs\Support\StateCollection;

final class OpportunityItemManualPriceReader
{
    public function __construct(
        private readonly StoresSnapshots $snapshots,
        private readonly StateManager $states,
    ) {}

    /**
     * Read manual overrides without registering unchanged states with Verbs.
     * Call inside the mutation transaction after locking the current item rows.
     *
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<int, int|null>
     */
    public function read(Opportunity $opportunity, Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        $stateIds = $items->map(fn (OpportunityItem $item): int => (int) $item->state_id)->values()->all();
        $loaded = $this->snapshots->load($stateIds, OpportunityItemState::class);
        $snapshotsById = [];

        if ($loaded instanceof StateCollection) {
            foreach ($loaded as $state) {
                if ($state instanceof OpportunityItemState) {
                    $snapshotsById['state:'.$state->id] = $state;
                }
            }
        }

        $latestEvents = VerbStateEvent::query()
            ->where('state_type', OpportunityItemState::class)
            ->whereIn('state_id', $stateIds)
            ->selectRaw('state_id, MAX(event_id) as latest_event_id')
            ->groupBy('state_id')
            ->pluck('latest_event_id', 'state_id');
        $manualPrices = [];

        foreach ($items as $item) {
            $stateId = (string) $item->state_id;
            $state = $snapshotsById['state:'.$stateId] ?? null;
            $latestEventId = $latestEvents->get($stateId);

            if ($state === null || $state->last_event_id === null || $latestEventId === null
                || (string) $state->last_event_id !== (string) $latestEventId
                || ! $this->matchesItem($state, $opportunity, $item)) {
                $state = $this->states->load((int) $item->state_id, OpportunityItemState::class);
            }

            if (! $state instanceof OpportunityItemState || ! $this->matchesItem($state, $opportunity, $item)) {
                throw new LogicException('An opportunity item does not match its pricing state.');
            }

            $manualPrices[(int) $item->id] = $state->manual_unit_price;
        }

        return $manualPrices;
    }

    private function matchesItem(OpportunityItemState $state, Opportunity $opportunity, OpportunityItem $item): bool
    {
        return (string) $state->id === (string) $item->state_id
            && $state->opportunity_item_id === (int) $item->id
            && $state->opportunity_id === (int) $opportunity->id
            && ! $state->is_removed;
    }
}

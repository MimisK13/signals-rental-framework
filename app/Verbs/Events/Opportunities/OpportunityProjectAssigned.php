<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Persists the Project grouping on the event-sourced Opportunity aggregate.
 * Project assignment is planning metadata; it never changes Store, dates,
 * availability, inventory, or financial totals.
 */
class OpportunityProjectAssigned extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public ?int $project_id,
    ) {}

    public function apply(OpportunityState $state): void
    {
        $state->project_id = $this->project_id;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->firstOrFail();
        $oldProjectId = $opportunity->project_id;

        $opportunity->update(['project_id' => $state->project_id]);

        $this->recordAudit(
            $opportunity,
            $state->project_id === null ? 'opportunity.project_detached' : 'opportunity.project_attached',
            newValues: ['project_id' => $state->project_id],
            oldValues: ['project_id' => $oldProjectId],
        );
    }
}

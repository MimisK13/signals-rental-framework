<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records that line discounts were reset to category rules during recalculation.
 */
class DiscountReset extends Event
{
    use RecordsOpportunityAudit;

    /**
     * @param  list<array{opportunity_item_id: int, discount_percent: ?string}>  $applications
     */
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public array $applications = [],
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity\'s discounts cannot be reset.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->first();

        if ($opportunity === null) {
            return;
        }

        $this->recordAudit(
            $opportunity,
            'opportunity.discount_reset',
            newValues: ['applications' => $this->applications],
            oldValues: null,
        );
    }
}

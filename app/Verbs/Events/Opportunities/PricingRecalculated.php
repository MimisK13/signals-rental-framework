<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records a full pricing pipeline recalculation on an opportunity.
 */
class PricingRecalculated extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public bool $reset_prices = false,
        public bool $reset_discounts = false,
        public int $charge_total_before = 0,
        public int $charge_total_after = 0,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity cannot be recalculated.',
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
            'opportunity.pricing_recalculated',
            newValues: [
                'reset_prices' => $this->reset_prices,
                'reset_discounts' => $this->reset_discounts,
                'charge_total_before' => $this->charge_total_before,
                'charge_total_after' => $this->charge_total_after,
            ],
            oldValues: [
                'charge_total' => $this->charge_total_before,
            ],
        );
    }
}

<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Records that an organisation discount category was applied (automatically or via
 * reset/re-apply) to one or more opportunity lines. The per-line discount values
 * are carried on companion {@see ItemDiscountSet} events in the same commit batch.
 */
class DiscountCategoryApplied extends Event
{
    use RecordsOpportunityAudit;

    /**
     * @param  list<array{opportunity_item_id: int, discount_percent: string}>  $applications
     */
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $discount_category_id,
        public array $applications = [],
        public bool $reset_manual = false,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity\'s discount category cannot be applied.',
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
            'opportunity.discount_category_applied',
            newValues: [
                'discount_category_id' => $this->discount_category_id,
                'applications' => $this->applications,
                'reset_manual' => $this->reset_manual,
            ],
            oldValues: null,
        );
    }
}

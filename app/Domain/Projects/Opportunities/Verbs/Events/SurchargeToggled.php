<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Verbs\Events;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunitySurchargeGroup;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\PricesOpportunityItems;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\RecordsOpportunityAudit;
use App\Domain\Projects\Opportunities\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Enables or disables a surcharge group on an opportunity, then reprices all lines.
 */
class SurchargeToggled extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $surcharge_group_id,
        public bool $is_active,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity\'s surcharge groups cannot be toggled.',
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

        OpportunitySurchargeGroup::query()->updateOrCreate(
            [
                'opportunity_id' => $opportunity->id,
                'surcharge_group_id' => $this->surcharge_group_id,
            ],
            ['is_active' => $this->is_active],
        );

        $opportunity->load('items');

        foreach ($opportunity->items as $item) {
            $this->repriceAndRollUp($item);
        }

        $this->recordAudit(
            $opportunity,
            'opportunity.surcharge_group_toggled',
            newValues: [
                'surcharge_group_id' => $this->surcharge_group_id,
                'is_active' => $this->is_active,
            ],
            oldValues: null,
        );
    }
}

<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Clears a group-level deal price and restores scoped line items to their
 * pre-group-deal discount percentages.
 */
class GroupDealPriceCleared extends Event
{
    use RecordsOpportunityAudit;

    /**
     * @param  list<array{opportunity_item_id: int, deal_price_discount_percent: null}>  $line_applications
     */
    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $group_opportunity_item_id,
        public array $line_applications = [],
        public bool $reprice = true,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assert(
            $state->itemType() === OpportunityItemType::Group,
            'Group deal price can only be cleared on a group line item.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->deal_price = null;
        $state->deal_price_original_total = null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $group = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($group === null) {
            return;
        }

        $opportunity = Opportunity::query()->find($group->opportunity_id);

        if ($opportunity === null) {
            return;
        }

        $oldDealPrice = $group->deal_price !== null ? (int) $group->deal_price : null;

        $group->forceFill([
            'deal_price' => null,
            'deal_price_original_total' => null,
        ])->saveQuietly();

        if ($this->reprice) {
            app(OpportunityTotalsCalculator::class)->repriceOpportunity($opportunity->fresh(['items.item', 'member']));
        }

        $opportunity->refresh();

        $this->recordAudit(
            $opportunity,
            'opportunity.group_deal_price_cleared',
            newValues: [
                'group_item_id' => $group->id,
                'deal_price' => null,
                'charge_total' => $opportunity->charge_total,
            ],
            oldValues: [
                'group_item_id' => $group->id,
                'deal_price' => $oldDealPrice,
            ],
        );
    }
}

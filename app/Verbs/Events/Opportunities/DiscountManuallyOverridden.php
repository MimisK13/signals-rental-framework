<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Audit event fired when an operator manually overrides a line's category discount.
 *
 * The discount value and `is_discount_manual` flag are set by the companion
 * {@see ItemDiscountSet} event in the same commit batch.
 */
class DiscountManuallyOverridden extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?string $discount_percent = null,
        public ?string $previous_discount_percent = null,
    ) {}

    public function apply(OpportunityItemState $state): void
    {
        $state->is_discount_manual = true;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $opportunity = $item->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $this->recordAudit(
            $opportunity,
            'opportunity.discount_manually_overridden',
            newValues: [
                'item_id' => $item->id,
                'discount_percent' => $this->discount_percent,
            ],
            oldValues: [
                'item_id' => $item->id,
                'discount_percent' => $this->previous_discount_percent,
            ],
        );
    }
}

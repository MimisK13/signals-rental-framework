<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\DealPriceMode;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Clears opportunity deal pricing and restores pre-deal line discount percentages.
 */
class DealPriceCleared extends Event
{
    use RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
    ) {}

    public function validate(OpportunityState $state): void
    {
        $this->assert(
            ! $state->isClosed(),
            'A closed opportunity\'s deal price cannot be changed.',
        );
    }

    public function apply(OpportunityState $state): void
    {
        $state->deal_total = null;
        $state->deal_price_mode = DealPriceMode::FlatHeadline->value;
        $state->deal_price_original_total = null;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->first();

        if ($opportunity === null) {
            return;
        }

        $oldDealTotal = $opportunity->deal_total !== null ? (int) $opportunity->deal_total : null;

        OpportunityItem::query()
            ->where('opportunity_id', $opportunity->id)
            ->whereNotNull('deal_price')
            ->update([
                'deal_price' => null,
                'deal_price_original_total' => null,
            ]);

        $opportunity->forceFill([
            'deal_total' => null,
            'deal_price_mode' => DealPriceMode::FlatHeadline->value,
            'deal_price_original_total' => null,
        ])->saveQuietly();

        app(OpportunityTotalsCalculator::class)->repriceOpportunity($opportunity->fresh(['items.item', 'member']));

        $opportunity->refresh();

        $this->recordAudit(
            $opportunity,
            'opportunity.deal_price_cleared',
            newValues: ['deal_total' => null, 'charge_total' => $opportunity->charge_total],
            oldValues: ['deal_total' => $oldDealTotal],
        );
    }
}

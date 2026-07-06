<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\DealPriceMode;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sets a manual deal-total override on the opportunity. Flat headline mode replaces
 * the engine-computed `charge_total` headline while leaving per-line nets untouched;
 * proportional mode distributes derived line discounts so lines sum to the deal price.
 *
 * Legacy events that carry only `deal_total` replay as flat headline mode.
 */
class DealPriceSet extends Event
{
    use RecordsOpportunityAudit;

    /**
     * @param  list<array{opportunity_item_id: int, deal_price_discount_percent: ?string}>  $line_applications
     */
    public function __construct(
        #[StateId(OpportunityState::class)]
        public int $opportunity_id,
        public int $deal_total = 0,
        public ?string $deal_price_mode = null,
        public ?int $deal_price_original_total = null,
        public array $line_applications = [],
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
        $state->deal_total = $this->deal_total;
        $state->deal_price_mode = $this->resolvedMode()->value;
        $state->deal_price_original_total = $this->deal_price_original_total;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityState $state): void
    {
        $opportunity = Opportunity::query()->where('state_id', $state->id)->first();

        if ($opportunity === null) {
            return;
        }

        $oldDealTotal = $opportunity->deal_total !== null ? (int) $opportunity->deal_total : null;
        $mode = $this->resolvedMode();

        $opportunity->forceFill([
            'deal_total' => $state->deal_total,
            'deal_price_mode' => $mode->value,
            'deal_price_original_total' => $this->deal_price_original_total,
        ])->saveQuietly();

        if ($mode === DealPriceMode::Proportional) {
            app(OpportunityTotalsCalculator::class)->repriceOpportunity($opportunity->fresh(['items.item', 'member']));
        } else {
            app(OpportunityTotalsCalculator::class)->rollUp($opportunity->refresh());
        }

        $opportunity->refresh();

        $this->recordAudit(
            $opportunity,
            'opportunity.deal_price_set',
            newValues: [
                'deal_total' => $opportunity->deal_total,
                'deal_price_mode' => $mode->value,
                'deal_price_original_total' => $this->deal_price_original_total,
                'line_applications' => $this->line_applications,
                'charge_total' => $opportunity->charge_total,
            ],
            oldValues: ['deal_total' => $oldDealTotal],
        );
    }

    private function resolvedMode(): DealPriceMode
    {
        if ($this->deal_price_mode === null || $this->deal_price_mode === '') {
            return DealPriceMode::FlatHeadline;
        }

        return DealPriceMode::from($this->deal_price_mode);
    }
}

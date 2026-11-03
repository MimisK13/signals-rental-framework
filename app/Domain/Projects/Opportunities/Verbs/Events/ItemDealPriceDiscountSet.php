<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Verbs\Events;

use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\PricesOpportunityItems;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\RecordsOpportunityAudit;
use App\Domain\Projects\Opportunities\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sets (or clears) the deal-price-derived discount percentage on a line item.
 */
class ItemDealPriceDiscountSet extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?string $deal_price_discount_percent = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->deal_price_discount_percent = $this->deal_price_discount_percent;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $item->forceFill([
            'deal_price_discount_percent' => $state->deal_price_discount_percent,
        ])->saveQuietly();

        $this->repriceAndRollUp($item, $state->manual_unit_price);
    }
}

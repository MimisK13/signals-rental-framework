<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Verbs\Events;

use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator;
use App\Domain\Projects\Opportunities\Verbs\Events\Concerns\RecordsOpportunityAudit;
use App\Domain\Projects\Opportunities\Verbs\States\OpportunityItemState;
use App\Enums\DealPriceMode;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sets a proportional deal price on a revenue group row and distributes derived
 * line discounts to scoped descendants.
 */
class GroupDealPriceSet extends Event
{
    use RecordsOpportunityAudit;

    /**
     * @param  list<array{opportunity_item_id: int, deal_price_discount_percent: ?string}>  $line_applications
     */
    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $group_opportunity_item_id,
        public int $deal_price,
        public int $deal_price_original_total,
        public DealPriceMode $deal_price_mode = DealPriceMode::Proportional,
        public array $line_applications = [],
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assert(
            $state->itemType() === OpportunityItemType::Group,
            'Group deal price can only be set on a group line item.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->deal_price = $this->deal_price;
        $state->deal_price_original_total = $this->deal_price_original_total;
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

        $group->forceFill([
            'deal_price' => $this->deal_price,
            'deal_price_original_total' => $this->deal_price_original_total,
        ])->saveQuietly();

        if ($opportunity->deal_total !== null) {
            $opportunity->forceFill([
                'deal_total' => null,
                'deal_price_mode' => DealPriceMode::FlatHeadline->value,
                'deal_price_original_total' => null,
            ])->saveQuietly();
        }

        app(OpportunityTotalsCalculator::class)->repriceOpportunity($opportunity->fresh(['items.item', 'member']));

        $this->recordAudit(
            $opportunity->refresh(),
            'opportunity.group_deal_price_set',
            newValues: [
                'group_item_id' => $group->id,
                'deal_price' => $this->deal_price,
                'deal_price_original_total' => $this->deal_price_original_total,
                'line_applications' => $this->line_applications,
            ],
            oldValues: null,
        );
    }
}

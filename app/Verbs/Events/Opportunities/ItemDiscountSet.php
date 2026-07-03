<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Sets (or clears) a line item's percentage discount. The discount is applied to
 * the net subtotal BEFORE tax; repricing recomputes the line + parent totals.
 */
class ItemDiscountSet extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public ?string $discount_percent = null,
        public bool $is_discount_manual = false,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->discount_percent = $this->discount_percent;
        $state->is_discount_manual = $this->is_discount_manual;
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldDiscount = $item->discount_percent !== null ? (string) $item->discount_percent : null;
        $oldManual = (bool) $item->is_discount_manual;

        $item->forceFill([
            'discount_percent' => $state->discount_percent,
            'is_discount_manual' => $state->is_discount_manual,
        ])->saveQuietly();

        $this->repriceAndRollUp($item, $state->manual_unit_price);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_discount_set',
                newValues: [
                    'item_id' => $item->id,
                    'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
                    'is_discount_manual' => $item->is_discount_manual,
                    'total' => $item->total,
                ],
                oldValues: [
                    'item_id' => $item->id,
                    'discount_percent' => $oldDiscount,
                    'is_discount_manual' => $oldManual,
                ],
            );
        }
    }
}

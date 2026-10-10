<?php

namespace App\Verbs\Events\Opportunities;

use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Brick\Math\BigDecimal;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Changes the authoritative event-sourced explicit reservation intent for an
 * equipment line. A zero value is an explicit release; null remains legacy-only.
 */
class ItemReservationChanged extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public string $reserved_quantity = '0',
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);

        $this->assert(
            OpportunityItemType::from($state->item_type)->generatesDemand()
                && $state->itemable_id !== null
                && $state->itemable_type !== null
                && ($state->itemable_type === Product::class || strtolower($state->itemable_type) === 'product'),
            'Only product equipment lines can have an explicit reservation.',
        );

        $this->assert(
            LineItemTransactionType::from($state->transaction_type) === LineItemTransactionType::Rental,
            'Only rental equipment lines can have an explicit reservation.',
        );

        $reserved = BigDecimal::of($this->reserved_quantity);
        $requested = BigDecimal::of($state->quantity);

        $this->assert($reserved->isGreaterThanOrEqualTo(0), 'The reserved quantity cannot be negative.');
        $this->assert(
            $reserved->isLessThanOrEqualTo($requested),
            'The reserved quantity cannot exceed the requested quantity.',
        );

        $allocated = OpportunityItemAsset::query()
            ->where('opportunity_item_id', $state->opportunity_item_id)
            ->whereNotNull('stock_level_id')
            ->count();
        $dispatched = BigDecimal::of($state->dispatched_quantity);

        $this->assert(
            $reserved->isGreaterThanOrEqualTo($allocated)
                && $reserved->isGreaterThanOrEqualTo($dispatched),
            'The reservation cannot be reduced below physically allocated or dispatched capacity.',
        );
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->reserved_quantity = BigDecimal::of($this->reserved_quantity)->toScale(2)->__toString();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $oldReservedQuantity = $item->reserved_quantity;

        $item->forceFill([
            'reserved_quantity' => $state->reserved_quantity,
        ])->saveQuietly();

        $this->syncDemand($item->refresh());

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $this->recordAudit(
                $opportunity,
                'opportunity.item_reservation_changed',
                newValues: [
                    'item_id' => $item->id,
                    'reserved_quantity' => (string) $item->reserved_quantity,
                ],
                oldValues: [
                    'item_id' => $item->id,
                    'reserved_quantity' => $oldReservedQuantity !== null ? (string) $oldReservedQuantity : null,
                ],
            );
        }
    }
}

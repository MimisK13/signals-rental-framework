<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\OpportunityItem;
use App\Models\OpportunityItemSurcharge;
use App\Models\ProductTaxClass;
use App\Models\Surcharge;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/**
 * Manually overrides, enables, or disables a surcharge on a single line item.
 */
class SurchargeOverridden extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public int $opportunity_item_id,
        public int $surcharge_id,
        public ?int $amount_minor = null,
        public bool $is_disabled = false,
        public ?int $product_tax_class_id = null,
    ) {}

    public function validate(OpportunityItemState $state): void
    {
        $this->assertItemMutable($state);
    }

    public function apply(OpportunityItemState $state): void
    {
        $state->last_event_at = CarbonImmutable::now();
    }

    public function handle(OpportunityItemState $state): void
    {
        $item = OpportunityItem::query()->whereKey($state->opportunity_item_id)->first();

        if ($item === null) {
            return;
        }

        $surcharge = Surcharge::query()->find($this->surcharge_id);

        if ($surcharge === null) {
            return;
        }

        $defaultTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');
        $taxClassId = $this->product_tax_class_id
            ?? $surcharge->product_tax_class_id
            ?? $defaultTaxClassId;

        OpportunityItemSurcharge::query()->updateOrCreate(
            [
                'opportunity_item_id' => $item->id,
                'surcharge_id' => $this->surcharge_id,
            ],
            [
                'amount' => $this->amount_minor ?? 0,
                'product_tax_class_id' => $taxClassId,
                'is_manual_override' => $this->amount_minor !== null && ! $this->is_disabled,
                'is_disabled' => $this->is_disabled,
            ],
        );

        $this->repriceAndRollUp($item, $state->manual_unit_price);

        $opportunity = $item->opportunity()->first();

        if ($opportunity !== null) {
            $item->refresh();

            $this->recordAudit(
                $opportunity,
                'opportunity.item_surcharge_overridden',
                newValues: [
                    'item_id' => $item->id,
                    'surcharge_id' => $this->surcharge_id,
                    'amount_minor' => $this->amount_minor,
                    'is_disabled' => $this->is_disabled,
                    'surcharge_total' => $item->surcharge_total,
                ],
                oldValues: null,
            );
        }
    }
}

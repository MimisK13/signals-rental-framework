<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Verbs\Events\DiscountManuallyOverridden;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemDiscountSet;

/**
 * Sets or clears a line item's percentage discount via the ItemDiscountSet event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.item.set_discount`
 * transition (Permission stage replaces the bare Gate::authorize the action used
 * to make).
 *
 * It deliberately does NOT declare `changes_rate`/`changes_tax`: a line discount
 * is a STRUCTURAL edit to the agreed NET basis, not a re-derivation of the exchange
 * rate or the tax rule. Per {@see App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator}
 * (class docblock, "LOCKING"), the FX/tax lock freezes only later FX-rate or
 * tax-rule re-pricing — structural/price edits on a locked order must STILL
 * recompute the net charge_total (at the frozen exchange-rate snapshot, without
 * re-deriving tax). So {@see App\Domain\Projects\Opportunities\Guards\Rules\FxTaxLockRule} must NOT
 * block this edit; the calculator's tax_locked branch keeps the tax figures frozen.
 */
class SetItemDiscount
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, SetItemDiscountData $data): OpportunityData
    {
        $opportunity = $item->opportunity()->firstOrFail();

        $this->commitVerbs(function () use ($item, $data, $opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.item.set_discount',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $previousDiscount = $item->discount_percent !== null ? (string) $item->discount_percent : null;

            ItemDiscountSet::fire(
                opportunity_item_id: $item->state_id,
                discount_percent: $data->discount_percent,
                is_discount_manual: true,
            );

            DiscountManuallyOverridden::fire(
                opportunity_item_id: $item->state_id,
                discount_percent: $data->discount_percent,
                previous_discount_percent: $previousDiscount,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

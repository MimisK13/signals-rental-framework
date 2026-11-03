<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\OverrideItemPriceData;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\OpportunityItemChargeBounds;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Domain\Projects\Opportunities\Verbs\Events\ItemPriceOverridden;
use Illuminate\Support\Facades\Gate;

/**
 * Sets or clears a line item's manual unit-price override via the
 * ItemPriceOverridden event.
 *
 * Runs the M7 {@see GuardPipeline} for the `opportunity.item.price_override`
 * transition (Permission stage replaces the bare Gate::authorize the action used
 * to make).
 *
 * It deliberately does NOT declare `changes_rate`/`changes_tax`: a manual
 * unit-price override is a STRUCTURAL edit to the agreed NET basis, not a re-
 * derivation of the exchange rate or the tax rule. Per
 * {@see App\Domain\Projects\Opportunities\Services\OpportunityTotalsCalculator} (class docblock,
 * "LOCKING"), the FX/tax lock freezes only later FX-rate or tax-rule re-pricing of
 * the already-agreed basis — structural/price edits on a locked order must STILL
 * recompute the net charge_total (at the frozen exchange-rate snapshot, without
 * re-deriving tax). So {@see App\Domain\Projects\Opportunities\Guards\Rules\FxTaxLockRule} must NOT
 * block this edit; the calculator's tax_locked branch keeps the tax figures frozen.
 */
class OverrideItemPrice
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, OverrideItemPriceData $data): OpportunityData
    {
        // Fast-path authorization BEFORE any business-logic read so an
        // unauthorized caller receives a 403, never a 422 bounds leak. The
        // GuardPipeline's Permission stage below remains the config-driven
        // authority for the transition itself.
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        app(OpportunityItemChargeBounds::class)->assertUnitPriceAndProjectedTotalFit(
            $item,
            $opportunity,
            $data->unit_price,
        );

        $this->commitVerbs(function () use ($item, $data, $opportunity): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.item.price_override',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            ItemPriceOverridden::fire(
                opportunity_item_id: $item->state_id,
                unit_price: $data->unit_price,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }
}

<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Services\Notifications\EventNotificationMapRegistry;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use App\Services\Opportunities\OpportunityItemManualPriceReader;
use App\Verbs\Events\Opportunities\ItemPricingBatchApplied;
use App\Verbs\States\OpportunityItemState;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Lifecycle\StateManager;

class ApplyItemPricingBatch
{
    use CommitsVerbsEvents;

    /**
     * Eligibility only. The action validates and authorizes independently.
     *
     * @param  array<mixed>  $mutations
     */
    public function supports(array $mutations): bool
    {
        if (count($mutations) < 2 || count($mutations) > 100) {
            return false;
        }

        foreach ($mutations as $mutation) {
            if (! is_array($mutation) || ! isset($mutation['id'], $mutation['field'])
                || ! array_key_exists('value', $mutation)
                || ! in_array($mutation['field'], ['unit_price', 'discount_percent'], true)) {
                return false;
            }
        }

        $map = app(EventNotificationMapRegistry::class);

        foreach (['opportunity.item_price_overridden', 'opportunity.item_discount_set', 'opportunity.discount_manually_overridden'] as $action) {
            if ($map->has($action)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $mutations
     */
    public function __invoke(Opportunity $opportunity, array $mutations): void
    {
        Gate::authorize('opportunities.edit');

        Validator::make(['mutations' => $mutations], [
            'mutations' => ['required', 'array', 'min:1', 'max:100'],
            'mutations.*' => ['required', 'array:id,field,value'],
            'mutations.*.id' => ['required', 'integer', 'min:1'],
            'mutations.*.field' => ['required', 'in:unit_price,discount_percent'],
            'mutations.*.value' => ['present', 'nullable', 'numeric', 'min:0'],
        ])->validate();

        $this->commitVerbs(function () use ($opportunity, $mutations): void {
            $opportunity = $this->lockModelForMutation($opportunity);

            if ($opportunity->statusEnum()->isClosed() || $opportunity->pricingFrozen()) {
                throw ValidationException::withMessages([
                    'opportunity' => 'Line items cannot be edited while the opportunity is closed or pricing is frozen.',
                ]);
            }

            $items = $opportunity->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currency = $opportunity->currency_code ?? settings('company.base_currency', 'GBP');
            $changes = [];

            foreach ($mutations as $mutation) {
                $itemId = (int) $mutation['id'];

                if (! $items->has($itemId)) {
                    throw ValidationException::withMessages(['item' => 'The line item could not be found.']);
                }

                $value = $mutation['value'];
                $value = $value === '' ? null : $value;

                if ($mutation['field'] === 'unit_price') {
                    $changes[$itemId]['unit_price'] = OverrideItemPriceData::from([
                        'currency' => $currency,
                        'unit_price' => $value === null ? null : (string) $value,
                    ])->unit_price;
                } else {
                    Validator::make(['discount_percent' => $value], [
                        'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                    ])->validate();
                    $changes[$itemId]['discount_percent'] = $value === null ? null : (string) $value;
                }
            }

            foreach ($changes as $itemId => $patch) {
                $item = clone $items->get($itemId);

                if (array_key_exists('discount_percent', $patch)) {
                    $item->discount_percent = $patch['discount_percent'];
                }

                foreach (array_keys($patch) as $field) {
                    app(GuardPipeline::class)->run(new TransitionContext(
                        transition: $field === 'unit_price' ? 'opportunity.item.price_override' : 'opportunity.item.set_discount',
                        opportunity: $opportunity,
                        permission: 'opportunities.edit',
                    ));
                }

                if (array_key_exists('unit_price', $patch)) {
                    app(OpportunityItemChargeBounds::class)->assertUnitPriceAndProjectedTotalFit(
                        $item, $opportunity, $patch['unit_price'],
                    );
                }
            }

            $manualPrices = app(OpportunityItemManualPriceReader::class)->read($opportunity, $items);
            $stateIds = $items->only(array_keys($changes))->map(fn ($item): int => (int) $item->state_id)->values()->all();
            // StateId discovers arrays one ID at a time. Prime its cache through
            // the bulk loader so snapshots are fetched together first.
            app(StateManager::class)->load($stateIds, OpportunityItemState::class);

            ItemPricingBatchApplied::fire(
                opportunity_item_ids: $stateIds,
                opportunity_id: (int) $opportunity->id,
                changes: $changes,
                manual_unit_prices: $manualPrices,
            );
        });
    }
}

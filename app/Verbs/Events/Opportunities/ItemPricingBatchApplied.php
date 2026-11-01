<?php

namespace App\Verbs\Events\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\Concerns\PricesOpportunityItems;
use App\Verbs\Events\Opportunities\Concerns\RecordsOpportunityAudit;
use App\Verbs\States\OpportunityItemState;
use Carbon\CarbonImmutable;
use LogicException;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Event;

/** A committed price/discount batch has one pricing pass and one before/after audit. */
class ItemPricingBatchApplied extends Event
{
    use PricesOpportunityItems, RecordsOpportunityAudit;

    /**
     * @param  list<int>  $opportunity_item_ids  Changed item states only.
     * @param  array<int, array{unit_price?: int|null, discount_percent?: string|null}>  $changes  Projection item IDs keyed to final field values.
     * @param  array<int, int|null>  $manual_unit_prices  Historical overrides for all current-version items before this batch.
     */
    public function __construct(
        #[StateId(OpportunityItemState::class)]
        public array $opportunity_item_ids,
        public int $opportunity_id,
        public array $changes,
        public array $manual_unit_prices = [],
    ) {}

    public function validate(): void
    {
        $this->assertOpportunityNotClosed($this->opportunity_id, 'line items');
        $knownIds = [];

        foreach ($this->states() as $state) {
            if (! $state instanceof OpportunityItemState) {
                continue;
            }

            $this->assert($state->opportunity_id === $this->opportunity_id, 'A line belongs to another opportunity.');
            $knownIds[] = $state->opportunity_item_id;

            if (array_key_exists($state->opportunity_item_id, $this->changes)) {
                $this->assert(! $state->is_removed, 'A removed line item cannot be modified.');
            }
        }

        $this->assert(array_diff(array_keys($this->changes), $knownIds) === [], 'A batch line item is missing.');
    }

    public function apply(): void
    {
        foreach ($this->states() as $state) {
            if (! $state instanceof OpportunityItemState) {
                continue;
            }

            $patch = $this->changes[$state->opportunity_item_id] ?? null;

            if ($patch === null) {
                continue;
            }

            if (array_key_exists('unit_price', $patch)) {
                $state->manual_unit_price = $patch['unit_price'];
            }

            if (array_key_exists('discount_percent', $patch)) {
                $state->discount_percent = $patch['discount_percent'];
                $state->is_discount_manual = true;
            }

            $state->last_event_at = CarbonImmutable::now();
        }
    }

    public function handle(): void
    {
        $opportunity = Opportunity::query()->findOrFail($this->opportunity_id);
        $items = $opportunity->items()->with('item')->orderBy('id')->get()->keyBy('id');
        $before = [];
        $manualPrices = $this->manual_unit_prices;

        foreach ($this->states() as $state) {
            if (! $state instanceof OpportunityItemState) {
                continue;
            }

            $item = $items->get($state->opportunity_item_id);

            if ($item === null) {
                throw new LogicException('A pricing batch item projection is missing.');
            }

            $manualPrices[$item->id] = $state->manual_unit_price;
            $patch = $this->changes[$item->id] ?? null;

            if ($patch === null) {
                continue;
            }

            $before[] = $this->pricingSnapshot($item);

            if (array_key_exists('discount_percent', $patch)) {
                $item->forceFill([
                    'discount_percent' => $state->discount_percent,
                    'is_discount_manual' => $state->is_discount_manual,
                ])->saveQuietly();
            }
        }

        $oldChargeTotal = (int) $opportunity->charge_total;
        $opportunity->setRelation('items', $items->values());
        app(OpportunityTotalsCalculator::class)->repriceOpportunity($opportunity, manualUnitPrices: $manualPrices);
        $after = $opportunity->items()->whereKey(array_keys($this->changes))->orderBy('id')->get()
            ->map(fn (OpportunityItem $item): array => $this->pricingSnapshot($item))->all();
        $opportunity->refresh();

        $this->recordAudit(
            $opportunity,
            'opportunity.item_pricing_batch_applied',
            newValues: ['items' => $after, 'charge_total' => (int) $opportunity->charge_total],
            oldValues: ['items' => $before, 'charge_total' => $oldChargeTotal],
        );
    }

    /** @return array{item_id: int, unit_price: int, discount_percent: string|null, is_discount_manual: bool, total: int} */
    private function pricingSnapshot(OpportunityItem $item): array
    {
        return [
            'item_id' => (int) $item->id,
            'unit_price' => (int) $item->unit_price,
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'is_discount_manual' => (bool) $item->is_discount_manual,
            'total' => (int) $item->total,
        ];
    }
}

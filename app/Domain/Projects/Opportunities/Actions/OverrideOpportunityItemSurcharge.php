<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Projects\Opportunities\Data\OpportunityData;
use App\Domain\Projects\Opportunities\Data\OverrideOpportunityItemSurchargeData;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Guards\GuardPipeline;
use App\Domain\Projects\Opportunities\Guards\TransitionContext;
use App\Models\Surcharge;
use App\Domain\Projects\Opportunities\Verbs\Events\SurchargeOverridden;
use Illuminate\Support\Facades\Gate;

class OverrideOpportunityItemSurcharge
{
    use CommitsVerbsEvents;

    public function __invoke(
        Opportunity $opportunity,
        OpportunityItem $item,
        OverrideOpportunityItemSurchargeData $data,
    ): OpportunityData {
        Gate::authorize('opportunities.edit');

        abort_unless((int) $item->opportunity_id === (int) $opportunity->id, 404);

        $this->commitVerbs(function () use ($opportunity, $item, $data): void {
            app(GuardPipeline::class)->run(new TransitionContext(
                transition: 'opportunity.override_item_surcharge',
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            $surcharge = Surcharge::query()->findOrFail($data->surcharge_id);
            $snapshot = $surcharge->snapshotAttributes();

            SurchargeOverridden::fire(
                opportunity_item_id: $item->state_id,
                surcharge_id: $data->surcharge_id,
                amount_minor: $data->amount_minor,
                is_disabled: $data->is_disabled,
                product_tax_class_id: $data->product_tax_class_id,
                snapshot_name: $snapshot['name'],
                snapshot_surcharge_type: $snapshot['surcharge_type'],
                snapshot_percent_value: $snapshot['percent_value'],
                snapshot_fixed_value_minor: $snapshot['fixed_value_minor'],
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items.itemSurcharges']));
    }
}

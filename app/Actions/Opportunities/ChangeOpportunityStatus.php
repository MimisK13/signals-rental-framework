<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityStatus;
use App\Guards\Opportunities\GuardPipeline;
use App\Guards\Opportunities\TransitionContext;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\ProductStoreCapacityLock;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use Illuminate\Support\Facades\Gate;

/**
 * Moves an opportunity to a different status within its current state via the
 * OpportunityStatusChanged event.
 *
 * Routed through the M7 {@see GuardPipeline} (Permission → Approval → Business
 * Rules → Plugin validators) before firing, so the `opportunity.change_status`
 * transition is permission-checked (`opportunities.edit`, exactly as before) and
 * rule-extensible, and reports correctly via {@see GuardPipeline::check()} in the
 * `available_actions` endpoint. No business rule is registered for this transition
 * today, so behaviour is unchanged. The hard invariants (e.g. a closed
 * opportunity cannot change status) stay in the event's Verbs `validate()`.
 */
class ChangeOpportunityStatus
{
    use CommitsVerbsEvents;

    /** The transition key this action drives through the guard pipeline. */
    public const string TRANSITION = 'opportunity.change_status';

    public function __invoke(Opportunity $opportunity, OpportunityStatus $status): OpportunityData
    {
        Gate::authorize('update', $opportunity);

        $this->commitVerbs(function () use ($opportunity, $status): void {
            $scopes = OpportunityItem::query()
                ->where('opportunity_id', $opportunity->id)
                ->get()
                ->map(function (OpportunityItem $item): ?array {
                    $item->setRelation('opportunity', $item->opportunity()->first());
                    $context = app(OpportunityItemDemandResolver::class)->resolveContext($item);

                    return $context['product_id'] !== null && $context['store_id'] !== null
                        ? ['product_id' => $context['product_id'], 'store_id' => $context['store_id']]
                        : null;
                })
                ->filter()
                ->values()
                ->all();
            app(ProductStoreCapacityLock::class)->acquireMany($scopes);

            app(GuardPipeline::class)->run(new TransitionContext(
                transition: self::TRANSITION,
                opportunity: $opportunity,
                permission: 'opportunities.edit',
            ));

            OpportunityStatusChanged::fire(
                opportunity_id: $opportunity->state_id,
                to_status: $status->statusValue(),
            );
        });

        return OpportunityData::fromModel($opportunity->refresh());
    }
}

<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Services\Opportunities\ItemTreeService;
use App\Services\Opportunities\OpportunityEditorTreeService;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Facades\Gate;

/**
 * Adds an empty free-text row without entering the product item pipeline.
 *
 * Text rows have no catalogue item, pricing, demand, or shortage state. This
 * action keeps the canonical authorization, tree allocation, and event path
 * while avoiding work that cannot affect the new row.
 */
class AddOpportunityTextItem
{
    use CommitsVerbsEvents;

    public function __invoke(Opportunity $opportunity, ?string $parentPath = null): int
    {
        Gate::authorize('opportunities.edit');

        app(OpportunityEditorTreeService::class)->assertCanNestUnder($parentPath);

        $versionId = $opportunity->active_version_id > 0
            ? $opportunity->active_version_id
            : null;
        $itemId = null;

        $this->commitVerbs(function () use ($opportunity, $parentPath, $versionId, &$itemId): void {
            $itemId = app(SequenceAllocator::class)->next('opportunity_items');
            $tree = app(ItemTreeService::class);
            $path = $parentPath !== null
                ? $tree->nextChildPath($opportunity->id, $versionId, $parentPath)
                : $tree->nextTopLevelPath($opportunity->id, $versionId);

            ItemAdded::fire(
                opportunity_item_id: (int) $itemId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                item_type: OpportunityItemType::Text->value,
                path: $path,
                name: '',
                quantity: '1',
                transaction_type: LineItemTransactionType::Rental->value,
                charge_period: ChargePeriod::Day->value,
            );
        });

        return (int) $itemId;
    }
}

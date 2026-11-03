<?php

declare(strict_types=1);

namespace App\Domain\Availability\Shortages\Actions;

use App\Domain\Availability\Shortages\Contracts\BatchShortageResolverContract;
use App\Domain\Availability\Shortages\Data\ApplyBatchResolutionData;
use App\Domain\Availability\Shortages\Services\ShortageBatchGrouper;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Availability\Shortages\Services\ShortageResolverRegistry;
use App\Domain\Availability\Shortages\ValueObjects\ResolutionResult;
use App\Domain\Availability\Shortages\ValueObjects\Shortage;
use App\Domain\Availability\Shortages\ValueObjects\ShortageCollection;
use App\Domain\Availability\Shortages\ValueObjects\ShortageGroup;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies a batch resolver option across multiple line items' shortages.
 */
class ApplyBatchShortageResolution
{
    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageResolverRegistry $registry,
        private readonly ShortageBatchGrouper $grouper,
    ) {}

    public function __invoke(ApplyBatchResolutionData $data): ResolutionResult
    {
        Gate::authorize('shortages.resolve');

        if (! $this->registry->has($data->resolver_key)) {
            throw ValidationException::withMessages([
                'resolver_key' => ["Unknown shortage resolver: {$data->resolver_key}."],
            ]);
        }

        $resolver = $this->registry->resolve($data->resolver_key);

        if (! $resolver instanceof BatchShortageResolverContract || ! $resolver->supportsBatch()) {
            throw ValidationException::withMessages([
                'resolver_key' => ["The {$resolver->name()} resolver does not support batch resolution."],
            ]);
        }

        $shortages = $this->detectShortages($data->opportunity_item_ids);
        $group = $this->resolveGroup($shortages, $data->opportunity_item_ids);

        if (! $resolver->canResolveBatch($group)) {
            throw ValidationException::withMessages([
                'resolver_key' => ["The {$resolver->name()} resolver is not applicable to this batch."],
            ]);
        }

        $options = $resolver->getBatchOptions($group);

        if (! array_key_exists($data->option_index, $options)) {
            throw ValidationException::withMessages([
                'option_index' => ['The selected batch resolution option is no longer available.'],
            ]);
        }

        return DB::transaction(function () use ($resolver, $group, $options, $data): ResolutionResult {
            OpportunityItem::query()
                ->whereIn('id', $data->opportunity_item_ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return $resolver->applyBatch($group, $options[$data->option_index]);
        });
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function detectShortages(array $itemIds): ShortageCollection
    {
        $shortages = new ShortageCollection;

        foreach ($itemIds as $itemId) {
            $item = OpportunityItem::query()->findOrFail($itemId);
            $shortage = $this->detector->forItem($item);

            if ($shortage === null) {
                throw ValidationException::withMessages([
                    'opportunity_item_ids' => ["Line item {$itemId} has no current shortage to resolve."],
                ]);
            }

            $shortages->push($shortage);
        }

        return $shortages;
    }

    /**
     * @param  list<int>  $requestedItemIds
     */
    private function resolveGroup(ShortageCollection $shortages, array $requestedItemIds): ShortageGroup
    {
        $groups = $this->grouper->groupsFrom($shortages);

        foreach ($groups as $group) {
            $groupItemIds = $group->opportunityItemIds();
            sort($groupItemIds);
            $requested = $requestedItemIds;
            sort($requested);

            if ($groupItemIds === $requested) {
                return $group;
            }
        }

        /** @var Shortage $first */
        $first = $shortages->first();
        $productId = $first->productId;
        $storeId = $first->storeId;

        foreach ($shortages as $shortage) {
            if ($shortage->productId !== $productId || $shortage->storeId !== $storeId) {
                throw ValidationException::withMessages([
                    'opportunity_item_ids' => ['Batch resolution requires the same product and store on every line.'],
                ]);
            }
        }

        throw ValidationException::withMessages([
            'opportunity_item_ids' => ['The selected line items do not form a valid batch group.'],
        ]);
    }
}

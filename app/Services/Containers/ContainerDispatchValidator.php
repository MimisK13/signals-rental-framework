<?php

namespace App\Services\Containers;

use App\Data\Containers\ContainerDispatchAmendmentData;
use App\Data\Containers\ContainerDispatchProductVerdictData;
use App\Enums\ContainerDispatchVerdict;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

/**
 * Validates a transport container manifest against an opportunity's line items and
 * allocations (serialised-containers.md §"Dispatch Validation Logic" steps 3–5).
 */
class ContainerDispatchValidator
{
    public function __construct(
        private readonly ContainerManifestService $manifests,
    ) {}

    /**
     * @param  list<ContainerDispatchAmendmentData>|null  $approvedAmendments
     * @return array{
     *     products: list<ContainerDispatchProductVerdictData>,
     *     has_failures: bool,
     *     has_prompts: bool,
     *     has_auto_adds: bool,
     *     auto_adds: list<ContainerDispatchProductVerdictData>,
     *     prompts: list<ContainerDispatchProductVerdictData>,
     *     failures: list<ContainerDispatchProductVerdictData>,
     * }
     */
    public function validate(
        Container $container,
        Opportunity $opportunity,
        User $actor,
        bool $autoAddMissing = true,
        ?array $approvedAmendments = null,
    ): array {
        return $this->validateProductQuantities(
            container: $container,
            opportunity: $opportunity,
            actor: $actor,
            productQuantities: $this->manifests->dispatchProductQuantities($container),
            autoAddMissing: $autoAddMissing,
            approvedAmendments: $approvedAmendments,
            useFullManifestForPlacement: true,
        );
    }

    /**
     * Kit/hybrid dispatch validation (serialised-containers.md §Step 7).
     *
     * Validates the kit housing product plus hybrid pool-binding contents only.
     * Fixed-binding contents are covered by the kit line item.
     *
     * @param  list<ContainerDispatchAmendmentData>|null  $approvedAmendments
     * @return array{
     *     products: list<ContainerDispatchProductVerdictData>,
     *     has_failures: bool,
     *     has_prompts: bool,
     *     has_auto_adds: bool,
     *     auto_adds: list<ContainerDispatchProductVerdictData>,
     *     prompts: list<ContainerDispatchProductVerdictData>,
     *     failures: list<ContainerDispatchProductVerdictData>,
     * }
     */
    public function validateKit(
        Container $container,
        Opportunity $opportunity,
        User $actor,
        bool $autoAddMissing = true,
        ?array $approvedAmendments = null,
    ): array {
        return $this->validateProductQuantities(
            container: $container,
            opportunity: $opportunity,
            actor: $actor,
            productQuantities: $this->manifests->kitDispatchProductQuantities($container),
            autoAddMissing: $autoAddMissing,
            approvedAmendments: $approvedAmendments,
            useFullManifestForPlacement: false,
        );
    }

    /**
     * @param  array<int, int>  $productQuantities
     * @param  list<ContainerDispatchAmendmentData>|null  $approvedAmendments
     * @return array{
     *     products: list<ContainerDispatchProductVerdictData>,
     *     has_failures: bool,
     *     has_prompts: bool,
     *     has_auto_adds: bool,
     *     auto_adds: list<ContainerDispatchProductVerdictData>,
     *     prompts: list<ContainerDispatchProductVerdictData>,
     *     failures: list<ContainerDispatchProductVerdictData>,
     * }
     */
    protected function validateProductQuantities(
        Container $container,
        Opportunity $opportunity,
        User $actor,
        array $productQuantities,
        bool $autoAddMissing,
        ?array $approvedAmendments,
        bool $useFullManifestForPlacement,
    ): array {
        $approvedByProduct = $this->indexApprovedAmendments($approvedAmendments);
        $canDispatchAddItems = $actor->can('opportunities.dispatch_add_items');

        $opportunity->loadMissing('items');
        $linesByProduct = $this->linesByProduct($opportunity);

        $products = [];
        $autoAdds = [];
        $prompts = [];
        $failures = [];

        foreach ($productQuantities as $productId => $containerQuantity) {
            $product = Product::query()->whereKey($productId)->first();
            $productName = $product !== null ? $product->name : (string) $productId;
            $lines = $linesByProduct->get($productId, collect());

            $orderedQuantity = $this->sumQuantity($lines);
            $allocatedQuantity = $this->countAllocated($lines);
            $unallocatedQuantity = max(0, (int) BigDecimal::of($orderedQuantity)
                ->toScale(0, RoundingMode::CEILING)
                ->toInt() - $allocatedQuantity);

            $alreadyOnOpportunity = $this->countManifestAssetsOnOpportunity(
                $container,
                $opportunity,
                $productId,
                $useFullManifestForPlacement,
            );

            $remainingToPlace = max(0, $containerQuantity - $alreadyOnOpportunity);

            $verdict = ContainerDispatchVerdict::Pass;
            $reason = null;
            $requiredAction = null;
            $suggestedQuantity = null;
            $suggestedPrice = null;

            if ($remainingToPlace === 0) {
                $verdict = ContainerDispatchVerdict::Pass;
            } elseif ($lines->isEmpty()) {
                if ($product?->is_warehouse_addable && $autoAddMissing) {
                    $verdict = ContainerDispatchVerdict::WarehouseAutoAdd;
                    $requiredAction = 'add';
                    $suggestedQuantity = (string) $containerQuantity;
                    $suggestedPrice = $product->warehouse_add_default_charge;
                } elseif ($canDispatchAddItems && isset($approvedByProduct[$productId])) {
                    $verdict = ContainerDispatchVerdict::Pass;
                } elseif ($canDispatchAddItems) {
                    $verdict = ContainerDispatchVerdict::Prompt;
                    $reason = 'not_on_opportunity';
                    $requiredAction = 'add';
                    $suggestedQuantity = (string) $containerQuantity;
                } else {
                    $verdict = ContainerDispatchVerdict::Fail;
                    $reason = 'not_on_opportunity';
                }
            } elseif ($remainingToPlace > $unallocatedQuantity) {
                $neededQuantity = (string) ($allocatedQuantity + $remainingToPlace);

                if ($canDispatchAddItems && isset($approvedByProduct[$productId])) {
                    $verdict = ContainerDispatchVerdict::Pass;
                } elseif ($canDispatchAddItems) {
                    $verdict = ContainerDispatchVerdict::Prompt;
                    $reason = 'exceeds_unallocated';
                    $requiredAction = 'increase';
                    $suggestedQuantity = $neededQuantity;
                } else {
                    $verdict = ContainerDispatchVerdict::Fail;
                    $reason = 'exceeds_unallocated';
                }
            } else {
                $verdict = ContainerDispatchVerdict::Pass;
            }

            $row = new ContainerDispatchProductVerdictData(
                product_id: $productId,
                product_name: $productName,
                verdict: $verdict->value,
                container_quantity: $containerQuantity,
                opportunity_quantity: $orderedQuantity,
                allocated_quantity: $allocatedQuantity,
                unallocated_quantity: $unallocatedQuantity,
                reason: $reason,
                required_action: $requiredAction,
                suggested_quantity: $suggestedQuantity,
                suggested_price: $suggestedPrice,
            );

            $products[] = $row;

            match ($verdict) {
                ContainerDispatchVerdict::WarehouseAutoAdd => $autoAdds[] = $row,
                ContainerDispatchVerdict::Prompt => $prompts[] = $row,
                ContainerDispatchVerdict::Fail => $failures[] = $row,
                default => null,
            };
        }

        return [
            'products' => $products,
            'has_failures' => $failures !== [],
            'has_prompts' => $prompts !== [],
            'has_auto_adds' => $autoAdds !== [],
            'auto_adds' => $autoAdds,
            'prompts' => $prompts,
            'failures' => $failures,
        ];
    }

    /**
     * @param  list<ContainerDispatchAmendmentData>|null  $amendments
     * @return array<int, ContainerDispatchAmendmentData>
     */
    protected function indexApprovedAmendments(?array $amendments): array
    {
        $indexed = [];

        foreach ($amendments ?? [] as $amendment) {
            $indexed[$amendment->product_id] = $amendment;
        }

        return $indexed;
    }

    /**
     * @return Collection<int, Collection<int, OpportunityItem>>
     */
    protected function linesByProduct(Opportunity $opportunity): Collection
    {
        /** @var array<int, Collection<int, OpportunityItem>> $grouped */
        $grouped = [];

        foreach ($opportunity->items as $item) {
            if ($item->itemable_type !== Product::class || $item->itemable_id === null) {
                continue;
            }

            $productId = (int) $item->itemable_id;
            if (! isset($grouped[$productId])) {
                $grouped[$productId] = new Collection([$item]);

                continue;
            }

            $grouped[$productId]->push($item);
        }

        return new Collection($grouped);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $lines
     */
    protected function sumQuantity(Collection $lines): string
    {
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            $total = $total->plus((string) $line->quantity);
        }

        return $total->toScale(2, RoundingMode::HALF_UP)->__toString();
    }

    /**
     * @param  Collection<int, OpportunityItem>  $lines
     */
    protected function countAllocated(Collection $lines): int
    {
        if ($lines->isEmpty()) {
            return 0;
        }

        return OpportunityItemAsset::query()
            ->whereIn('opportunity_item_id', $lines->pluck('id'))
            ->count();
    }

    protected function countManifestAssetsOnOpportunity(
        Container $container,
        Opportunity $opportunity,
        int $productId,
        bool $useFullManifest = true,
    ): int {
        $manifestAssets = $useFullManifest
            ? $this->manifests->dispatchAssets($container)
            : $this->manifests->kitDispatchAssets($container);

        $stockLevelIds = collect($manifestAssets)
            ->filter(fn (array $asset): bool => (int) $asset['product_id'] === $productId)
            ->pluck('serialised_item_id');

        if ($stockLevelIds->isEmpty()) {
            return 0;
        }

        return OpportunityItemAsset::query()
            ->whereIn('stock_level_id', $stockLevelIds)
            ->whereHas('item', fn ($query) => $query->where('opportunity_id', $opportunity->id))
            ->count();
    }
}

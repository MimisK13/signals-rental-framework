<?php

namespace App\Actions\Opportunities;

use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\KitComponentBinding;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expands a Product composition into the opportunity's ordinary item tree.
 *
 * The structural parent is a Group, so it creates no Demand. Pool components
 * become ordinary child items and therefore remain independently editable,
 * reservable, and allocatable. Fixed components remain container-owned under
 * the existing kit semantics.
 */
class ExpandOpportunityCombination
{
    public function __invoke(
        Opportunity $opportunity,
        Product $product,
        string $quantity = '1',
        ?string $parentPath = null,
    ): OpportunityData {
        if ($product->components()->doesntExist()) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product has no expandable composition.'],
            ]);
        }

        if (bccomp($quantity, '0', 2) < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['The quantity must be zero or greater.'],
            ]);
        }

        DB::transaction(function () use ($opportunity, $product, $quantity, $parentPath): void {
            $this->expand(
                opportunity: $opportunity,
                product: $product->load('components.componentProduct.components'),
                quantity: $quantity,
                parentPath: $parentPath,
                ancestry: [$product->id],
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }

    /**
     * @param  list<int>  $ancestry
     */
    private function expand(
        Opportunity $opportunity,
        Product $product,
        string $quantity,
        ?string $parentPath,
        array $ancestry,
    ): void {
        (new AddOpportunityGroup)(
            $opportunity,
            AddOpportunityGroupData::from([
                'name' => $product->name,
                'quantity' => $quantity,
                'parent_path' => $parentPath,
                'custom_fields' => ['combination_product_id' => $product->id],
            ]),
        );

        $groupPath = $opportunity->fresh(['items'])->items
            ->where('item_type', OpportunityItemType::Group)
            ->sortByDesc('id')
            ->first()?->path;

        if ($groupPath === null) {
            throw new \RuntimeException('Expanded combination parent was not projected.');
        }

        $this->expandComponents(
            opportunity: $opportunity,
            product: $product,
            quantity: $quantity,
            parentPath: $groupPath,
            ancestry: $ancestry,
        );
    }

    /**
     * @param  list<int>  $ancestry
     */
    private function expandComponents(
        Opportunity $opportunity,
        Product $product,
        string $quantity,
        string $parentPath,
        array $ancestry,
    ): void {
        foreach ($product->components()->with('componentProduct')->orderBy('sort_order')->orderBy('id')->get() as $component) {
            if ($component->binding === KitComponentBinding::Fixed) {
                continue;
            }

            $componentProduct = $component->componentProduct;
            if ($componentProduct === null) {
                continue;
            }

            $childQuantity = bcmul($quantity, (string) $component->quantity, 2);

            if ($componentProduct->isKit() && $componentProduct->components()->exists()) {
                if (in_array($componentProduct->id, $ancestry, true)) {
                    throw ValidationException::withMessages([
                        'product_id' => ['The product composition contains a circular expansion.'],
                    ]);
                }

                $this->expand(
                    opportunity: $opportunity,
                    product: $componentProduct,
                    quantity: $childQuantity,
                    parentPath: $parentPath,
                    ancestry: [...$ancestry, $componentProduct->id],
                );

                continue;
            }

            (new AddOpportunityItem)(
                $opportunity,
                AddOpportunityItemData::from([
                    'name' => $componentProduct->name,
                    'itemable_id' => $componentProduct->id,
                    'itemable_type' => Product::class,
                    'item_type' => OpportunityItemType::Product->value,
                    'parent_path' => $parentPath,
                    'quantity' => $childQuantity,
                    'materialize_included_accessories' => false,
                ]),
            );
        }
    }
}

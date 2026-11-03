<?php

declare(strict_types=1);

namespace App\Domain\Projects\Opportunities\Presentation\Livewire;

use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\OpportunityItemDemandResolver;
use App\Domain\Availability\Services\RecalculationPipeline;
use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityAccessory;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityGroup;
use App\Domain\Projects\Opportunities\Actions\AddOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\ApplyDiscountCategory;
use App\Domain\Projects\Opportunities\Actions\ChangeItemDates;
use App\Domain\Projects\Opportunities\Actions\ChangeItemQuantity;
use App\Domain\Projects\Opportunities\Actions\ClearDealPrice;
use App\Domain\Projects\Opportunities\Actions\ExpandOpportunityCombination;
use App\Domain\Projects\Opportunities\Actions\MergeOpportunityItems;
use App\Domain\Projects\Opportunities\Actions\OverrideItemPrice;
use App\Domain\Projects\Opportunities\Actions\RecalculateOpportunity;
use App\Domain\Projects\Opportunities\Actions\RemoveOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\RenameOpportunityItem;
use App\Domain\Projects\Opportunities\Actions\RestructureOpportunityItems;
use App\Domain\Projects\Opportunities\Actions\SetDealPrice;
use App\Domain\Projects\Opportunities\Actions\SetItemDiscount;
use App\Domain\Projects\Opportunities\Actions\SubstituteItem;
use App\Domain\Projects\Opportunities\Actions\ToggleItemOptional;
use App\Domain\Projects\Opportunities\Actions\UpdateOpportunityItemDetails;
use App\Domain\Projects\Opportunities\Contracts\OpportunityLineItemsEditorContract;
use App\Domain\Projects\Opportunities\Data\AddOpportunityAccessoryData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityGroupData;
use App\Domain\Projects\Opportunities\Data\AddOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\ApplyDiscountCategoryData;
use App\Domain\Projects\Opportunities\Data\ChangeItemDatesData;
use App\Domain\Projects\Opportunities\Data\ChangeItemQuantityData;
use App\Domain\Projects\Opportunities\Data\MergeOpportunityItemsData;
use App\Domain\Projects\Opportunities\Data\OpportunityItemReadContext;
use App\Domain\Projects\Opportunities\Data\OverrideItemPriceData;
use App\Domain\Projects\Opportunities\Data\PreparedProductInputs;
use App\Domain\Projects\Opportunities\Data\PreparedStandardDemandInputs;
use App\Domain\Projects\Opportunities\Data\PreparedStockInputs;
use App\Domain\Projects\Opportunities\Data\RecalculateOpportunityData;
use App\Domain\Projects\Opportunities\Data\RenameOpportunityItemData;
use App\Domain\Projects\Opportunities\Data\RestructureOpportunityItemsData;
use App\Domain\Projects\Opportunities\Data\SetDealPriceData;
use App\Domain\Projects\Opportunities\Data\SetItemDiscountData;
use App\Domain\Projects\Opportunities\Data\SubstituteItemData;
use App\Domain\Projects\Opportunities\Data\ToggleItemOptionalData;
use App\Domain\Projects\Opportunities\Data\UpdateOpportunityItemDetailsData;
use App\Domain\Projects\Opportunities\Enums\OpportunityItemType;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItem;
use App\Domain\Projects\Opportunities\Services\LineItemTreeReconciler;
use App\Domain\Projects\Opportunities\Services\OpportunityEditorTreeService;
use App\Domain\Projects\Opportunities\Services\OpportunityLineItemsTreeBuilder;
use App\Domain\Projects\Opportunities\Services\OpportunityLineItemTreeRevision;
use App\Domain\Projects\Opportunities\Services\ProductSearchService;
use App\Domain\Projects\Planning\Services\OpportunityItemPlanningReader;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Production local-first line-item editor (Phase 5). Embedded in the opportunity
 * Show overview; Alpine owns the table under wire:ignore, Livewire exposes the
 * server contract only.
 */
final class LineItems extends Component implements OpportunityLineItemsEditorContract
{
    public Opportunity $opportunity;

    /** @var array<int, array<string, mixed>> */
    public array $catalogue = [];

    public string $quickAddDestination = '';

    public bool $editable = false;

    public bool $fieldsEditable = false;

    public string $newSectionName = '';

    public string $newSectionParent = '';

    public string $newTextLineName = '';

    private ?Opportunity $itemsReadOpportunity = null;

    /** @var EloquentCollection<int, OpportunityItem>|null */
    private ?EloquentCollection $itemsRead = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('opportunities.view');

        $this->opportunity = $opportunity;
        $this->editable = Gate::allows('opportunities.edit') && ! $opportunity->statusEnum()->isClosed();
        $this->fieldsEditable = $this->editable && ! $opportunity->pricingFrozen();
        $this->catalogue = app(ProductSearchService::class)->catalogueIndex($opportunity->store_id);
    }

    #[On('echo-private:availability.opportunity.{opportunity.id},.availability.changed')]
    public function onAvailabilityChanged(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $this->invalidateItemsRead();
        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function tree(): array
    {
        [$opportunity, $items] = $this->sharedItemsRead();
        [$contexts, $products, $preparedStock, $preparedDemand] = $this->prepareLineItemsRead($opportunity, $items);
        $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity, $items, $contexts, $products, $preparedStock, $preparedDemand);
        $planning = app(OpportunityItemPlanningReader::class)
            ->forOpportunity($opportunity, $items, $contexts, $products, $preparedStock, $preparedDemand)
            ->keyBy('opportunity_item_id');

        $this->dispatch(
            'opportunity-shortage-summary-updated',
            opportunityId: $opportunity->id,
            unresolvedCount: $planning->where('has_shortage', true)->count(),
        );

        return array_map(function (array $row) use ($planning): array {
            $row['planning'] = $planning->get((int) $row['id'])?->toArray();

            return $row;
        }, $tree);
    }

    public function treeRevision(): int
    {
        return app(OpportunityLineItemTreeRevision::class)->current($this->opportunity->id);
    }

    #[Renderless]
    public function treeRevisionToken(): string
    {
        return (string) $this->treeRevision();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function destinations(): array
    {
        [$opportunity, $items] = $this->sharedItemsRead();

        return app(OpportunityLineItemsTreeBuilder::class)->destinations($opportunity, $items);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    #[Computed]
    public function sectionOptions(): array
    {
        [$opportunity, $items] = $this->sharedItemsRead();

        return app(OpportunityLineItemsTreeBuilder::class)->parentGroupOptions($opportunity, $items);
    }

    /** @return array<int, array{value: string, label: string}> */
    #[Computed]
    public function parentGroupOptions(): array
    {
        return $this->sectionOptions();
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function searchProducts(string $query): array
    {
        Gate::authorize('products.read');

        return app(ProductSearchService::class)
            ->search($query, $this->opportunity->store_id, 12)
            ->map(fn ($result) => $result->toArray())
            ->all();
    }

    #[Renderless]
    public function addProduct(int $productId, float $quantity = 1, ?string $destination = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($productId, $quantity, $destination): void {
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The selected product could not be found.',
                ]);
            }

            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $parentPath = $this->resolveParentPathForAdd(
                $opportunity,
                $opportunity->items,
                $product,
                $destination ?? $this->quickAddDestination,
            );

            $itemPayload = [
                'name' => $product->name,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => (string) max(0.01, $quantity),
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'parent_path' => $parentPath,
            ];

            if ($this->opportunity->pricingFrozen()) {
                $itemPayload['unit_price'] = 0;
            }

            (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($itemPayload));

            $this->refreshOpportunity();
        }, 'Item added');
    }

    #[Renderless]
    public function quickAdd(int $productId, float $quantity = 1): void
    {
        $this->addProduct($productId, $quantity, $this->quickAddDestination);
    }

    #[Renderless]
    public function addCombination(int $productId, float $quantity = 1, ?string $destination = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($productId, $quantity, $destination): void {
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The selected combination could not be found.',
                ]);
            }

            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $parentPath = $this->resolveParentPathForCombination(
                $opportunity->items,
                $destination ?? $this->quickAddDestination,
            );

            (new ExpandOpportunityCombination)(
                $opportunity,
                $product,
                (string) max(0.01, $quantity),
                $parentPath,
            );

            $this->refreshOpportunity();
        }, 'Combination added');
    }

    public function addTextLine(): void
    {
        $this->guardEditable();

        $name = trim($this->newTextLineName);

        if ($name === '') {
            $this->addError('newTextLineName', 'Enter a name for the free text item.');

            return;
        }

        try {
            $this->runMutation(function () use ($name): void {
                $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;

                $payload = [
                    'name' => $name,
                    'item_type' => OpportunityItemType::Text->value,
                    'quantity' => '1',
                    'parent_path' => $this->resolveParentPathForText(
                        $opportunity->items,
                        $this->quickAddDestination,
                    ),
                ];

                if ($this->opportunity->pricingFrozen()) {
                    $payload['unit_price'] = 0;
                    $payload['currency'] = $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP');
                }

                (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($payload));

                $this->refreshOpportunity();
            }, 'Free text item added', syncTree: true, mutationMeta: ['modal' => 'add-text-line']);

            $this->newTextLineName = '';
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    #[Renderless]
    public function addInlineTextLine(): int
    {
        $this->guardEditable();

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $beforeIds = $opportunity->items->pluck('id');

        $payload = [
            'name' => '',
            'item_type' => OpportunityItemType::Text->value,
            'quantity' => '1',
            'parent_path' => $this->resolveParentPathForText(
                $opportunity->items,
                $this->quickAddDestination,
            ),
        ];

        if ($this->opportunity->pricingFrozen()) {
            $payload['unit_price'] = 0;
            $payload['currency'] = $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP');
        }

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from($payload));

        $this->refreshOpportunity();

        $fresh = $this->opportunity->fresh(['items']) ?? $this->opportunity;

        $newItem = $fresh->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Text
                && ! $beforeIds->contains($item->id))
            ->sortByDesc('id')
            ->first();

        return (int) ($newItem->id);
    }

    #[Renderless]
    public function addInlineSection(): int
    {
        return $this->addGroup(null, '');
    }

    #[Renderless]
    public function addGroup(?int $parentGroupId = null, string $name = ''): int
    {
        $this->guardEditable();

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $beforeIds = $opportunity->items->pluck('id');
        $parentPath = $parentGroupId === null
            ? null
            : $this->treeService()->parentPathForGroupId($opportunity->items, $parentGroupId);

        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
            'name' => $name,
            'parent_path' => $parentPath,
        ]));

        $this->refreshOpportunity();

        $fresh = $this->opportunity->fresh(['items']) ?? $this->opportunity;

        $newGroup = $fresh->items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group
                && ! $beforeIds->contains($item->id))
            ->sortByDesc('id')
            ->first();

        return (int) ($newGroup->id);
    }

    public function addAccessory(int $principalItemId, int $productId, float $quantity = 1): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($principalItemId, $productId, $quantity): void {
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The selected product could not be found.',
                ]);
            }

            (new AddOpportunityAccessory)($this->opportunity->fresh(['items']) ?? $this->opportunity, AddOpportunityAccessoryData::from([
                'name' => $product->name,
                'principal_item_id' => $principalItemId,
                'itemable_id' => $product->id,
                'itemable_type' => Product::class,
                'quantity' => (string) max(0, $quantity),
            ]));

            $this->refreshOpportunity();
        }, 'Accessory added');
    }

    public function renameItem(int $itemId, string $name): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $name): void {
            $item = $this->findItem($itemId);
            (new RenameOpportunityItem)($item, RenameOpportunityItemData::from(['name' => trim($name)]));

            $this->refreshOpportunity();
        }, 'Renamed', syncTree: true, mutationMeta: ['modalId' => 'rename-section']);
    }

    public function saveLineEdits(
        int $itemId,
        ?string $unitPrice = null,
        ?string $discount = null,
        ?string $startsAt = null,
        ?string $endsAt = null,
        ?string $description = null,
        ?string $notes = null,
    ): void {
        $this->guardEditable();

        $args = func_get_args();
        $argumentCount = func_num_args();

        $this->runMutation(function () use ($itemId, $unitPrice, $discount, $startsAt, $endsAt, $description, $notes, $args, $argumentCount): void {
            $item = $this->findItem($itemId);

            if (! $this->opportunity->pricingFrozen()) {
                (new OverrideItemPrice)($item, OverrideItemPriceData::from([
                    'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                    'unit_price' => $unitPrice === null || $unitPrice === '' ? null : $unitPrice,
                ]));

                (new SetItemDiscount)($item, SetItemDiscountData::from([
                    'discount_percent' => $discount === null || $discount === '' ? null : $discount,
                ]));

                (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => $startsAt === '' ? null : $startsAt,
                    'ends_at' => $endsAt === '' ? null : $endsAt,
                ]));
            }

            $detailsPayload = [];

            if (array_key_exists(5, $args)) {
                $detailsPayload['description'] = $description === '' ? null : $description;
            }

            if ($argumentCount > 6) {
                $detailsPayload['notes'] = $notes === '' ? null : $notes;
            }

            if ($detailsPayload !== []) {
                (new UpdateOpportunityItemDetails)($item->fresh(), UpdateOpportunityItemDetailsData::from($detailsPayload));
            }

            $this->refreshOpportunity();
        }, 'Line updated', syncTree: true, mutationMeta: ['modalId' => 'edit-line']);
    }

    #[Renderless]
    public function updateField(int $id, string $field, mixed $value): void
    {
        $this->guardEditable();
        $this->guardFieldsEditable($field);

        $this->runMutation(function () use ($id, $field, $value): void {
            $item = $this->findItem($id);

            match ($field) {
                'quantity' => (new ChangeItemQuantity)($item, ChangeItemQuantityData::from([
                    'quantity' => (string) $value,
                ])),
                'unit_price' => (new OverrideItemPrice)($item, OverrideItemPriceData::from([
                    'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                    'unit_price' => $value === null || $value === '' ? null : (string) $value,
                ])),
                'discount_percent' => (new SetItemDiscount)($item, SetItemDiscountData::from([
                    'discount_percent' => $value === null || $value === '' ? null : (string) $value,
                ])),
                'name' => $this->updateNameField($item, (string) $value),
                'starts_at' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => $value === '' ? null : $value,
                    'ends_at' => optional($item->ends_at)?->toDateString(),
                ])),
                'ends_at' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => optional($item->starts_at)?->toDateString(),
                    'ends_at' => $value === '' ? null : $value,
                ])),
                'dates' => (new ChangeItemDates)($item, ChangeItemDatesData::from([
                    'starts_at' => is_array($value) ? ($value['starts_at'] ?? null) : null,
                    'ends_at' => is_array($value) ? ($value['ends_at'] ?? null) : null,
                ])),
                'days' => $this->changeItemDaysAndReturn($item, (int) $value),
                default => throw ValidationException::withMessages([
                    'field' => "Unknown field: {$field}",
                ]),
            };

            $this->refreshOpportunity();
        });
    }

    #[Renderless]
    public function toggleOptional(int $itemId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId): void {
            $item = $this->findItem($itemId);
            $nowOptional = ! $item->is_optional;

            (new ToggleItemOptional)($item, ToggleItemOptionalData::from([
                'is_optional' => $nowOptional,
            ]));

            $this->refreshOpportunity();

            $this->dispatch('toast', type: 'success', message: $nowOptional ? 'Marked optional' : 'Marked required');
        }, syncTree: true);
    }

    #[Renderless]
    public function substituteItem(int $itemId, int $productId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $productId): void {
            $item = $this->findItem($itemId);
            $product = Product::query()->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product' => 'The replacement product could not be found.',
                ]);
            }

            (new SubstituteItem)($item, SubstituteItemData::from([
                'item_id' => $product->id,
                'itemable_type' => Product::class,
                'name' => $product->name,
            ]));

            $this->refreshOpportunity();
        }, 'Product substituted', syncTree: true);
    }

    public function removeItem(int $itemId): void
    {
        $this->guardEditable();
        $item = $this->findItem($itemId);
        $this->guardPricingFrozenForRemoval($item);

        $this->runMutation(function () use ($item): void {
            (new RemoveOpportunityItem)($item);

            $this->refreshOpportunity();
        }, 'Item removed');
    }

    /**
     * Apply a deliberate editor tree restructure. The node list is authoritative
     * user intent — we always apply when validation passes. Omitted node ids are
     * removed server-side (`prune_orphans`) so local-first deletes persist through
     * this path. {@see $baseRevision} is advisory (revision drift detection / logging only).
     *
     * @param  array<int, array{id: int, depth: int}>  $nodes
     * @return array{stale: bool, revision: string, revision_drift: bool, base_revision: string, server_revision_before: string}
     */
    #[Renderless]
    public function persistTree(array $nodes, int $baseRevision = 0): array
    {
        $this->guardEditable();
        $this->guardPricingFrozenForTreePrune($nodes);

        $serverRevision = $this->treeRevision();
        $revisionDrift = $baseRevision > 0 && $serverRevision > $baseRevision;

        (new RestructureOpportunityItems)(
            $this->opportunity->fresh(['items']) ?? $this->opportunity,
            RestructureOpportunityItemsData::from([
                'nodes' => $nodes,
                'prune_orphans' => true,
            ]),
        );

        $this->refreshOpportunity();

        return [
            'stale' => false,
            'revision' => (string) $this->treeRevision(),
            'revision_drift' => $revisionDrift,
            'base_revision' => (string) $baseRevision,
            'server_revision_before' => (string) $serverRevision,
        ];
    }

    #[Renderless]
    public function mergeDuplicates(int $survivorId): void
    {
        $this->guardEditable();
        $survivor = $this->findItem($survivorId);
        $duplicateIds = $this->duplicateIdsFor($survivor);

        foreach ($duplicateIds as $duplicateId) {
            $this->guardPricingFrozenForRemoval($this->findItem($duplicateId));
        }

        $this->runMutation(function () use ($survivor, $duplicateIds): void {
            if ($duplicateIds === []) {
                return;
            }

            (new MergeOpportunityItems)($survivor, MergeOpportunityItemsData::from([
                'duplicate_item_ids' => $duplicateIds,
            ]));

            $this->refreshOpportunity();
        }, 'Duplicates merged', syncTree: true);
    }

    #[Renderless]
    public function assignToGroup(int $itemId, ?int $groupId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $groupId): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $destination = $groupId === null ? null : 'group:'.$groupId;
            $nodes = $tree->nodesAfterMovingLine($opportunity->items, $itemId, 0, $destination);
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();

            $label = $groupId === null
                ? 'auto group'
                : $this->findGroup($groupId)->name;

            $this->dispatch('toast', type: 'success', message: 'Moved to '.$label);
        }, syncTree: true);
    }

    #[Renderless]
    public function setDealPrice(string $dealTotal, string $dealPriceMode = 'flat_headline'): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($dealTotal, $dealPriceMode): void {
            (new SetDealPrice)($this->opportunity, SetDealPriceData::from([
                'currency' => $this->opportunity->currency_code ?? settings('company.base_currency', 'GBP'),
                'deal_total' => $dealTotal,
                'deal_price_mode' => $dealPriceMode,
            ]));

            $this->refreshOpportunity();
        }, 'Deal price set', syncTree: true);
    }

    #[Renderless]
    public function recalculatePricing(bool $resetPrices = false, bool $resetDiscounts = false): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($resetPrices, $resetDiscounts): void {
            (new RecalculateOpportunity)($this->opportunity, RecalculateOpportunityData::from([
                'reset_prices' => $resetPrices,
                'reset_discounts' => $resetDiscounts,
            ]));

            $this->refreshOpportunity();
        }, 'Pricing recalculated', syncTree: true);
    }

    #[Renderless]
    public function clearDealPrice(): void
    {
        $this->guardEditable();

        $this->runMutation(function (): void {
            (new ClearDealPrice)($this->opportunity);

            $this->refreshOpportunity();
        }, 'Deal price cleared', syncTree: true);
    }

    public function updateQuantity(int $itemId, string $quantity): void
    {
        $this->updateField($itemId, 'quantity', $quantity);
    }

    public function overridePrice(int $itemId, ?string $unitPrice): void
    {
        $this->updateField($itemId, 'unit_price', $unitPrice);
    }

    public function setDiscount(int $itemId, ?string $discountPercent): void
    {
        $this->updateField($itemId, 'discount_percent', $discountPercent);
    }

    #[Renderless]
    public function resetItemDiscount(int $itemId): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId): void {
            (new ApplyDiscountCategory)(
                $this->opportunity,
                ApplyDiscountCategoryData::from([
                    'opportunity_item_ids' => [$itemId],
                    'reset_manual' => true,
                ]),
            );

            $this->refreshOpportunity();
        }, 'Category discount re-applied', syncTree: true);
    }

    public function changeDates(int $itemId, ?string $startsAt, ?string $endsAt): void
    {
        $this->guardEditable();
        $this->guardFieldsEditable('dates');

        $this->runMutation(function () use ($itemId, $startsAt, $endsAt): void {
            $item = $this->findItem($itemId);
            (new ChangeItemDates)($item, ChangeItemDatesData::from([
                'starts_at' => $startsAt === '' ? null : $startsAt,
                'ends_at' => $endsAt === '' ? null : $endsAt,
            ]));

            $this->refreshOpportunity();
        }, 'Dates updated');
    }

    public function assignToSection(int $itemId, ?int $groupId): void
    {
        $this->assignToGroup($itemId, $groupId);
    }

    public function handleSort(int $itemId, int $position, ?string $groupId = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($itemId, $position, $groupId): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $nodes = $tree->nodesAfterMovingLine(
                $opportunity->items,
                $itemId,
                $position,
                $this->normalizeGroupKey($groupId),
            );
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();
        });
    }

    public function handleSectionSort(int $groupId, int $position, ?string $parentGroupKey = null): void
    {
        $this->guardEditable();

        $this->runMutation(function () use ($groupId, $position, $parentGroupKey): void {
            $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
            $tree = $this->treeService();
            $nodes = $tree->nodesAfterMovingGroup(
                $opportunity->items,
                $groupId,
                $position,
                $this->normalizeParentGroupKey($parentGroupKey),
            );
            $tree->restructure($opportunity, $nodes);

            $this->refreshOpportunity();
        });
    }

    /**
     * @param  array<int, int|string>  $groupIds
     */
    public function reorderSections(array $groupIds): void
    {
        $this->guardEditable();

        $groupIds = array_values(array_map('intval', $groupIds));

        if ($groupIds === []) {
            return;
        }

        $opportunity = $this->opportunity->fresh(['items']) ?? $this->opportunity;
        $items = $opportunity->items;
        $tree = $this->treeService();
        $first = $items->firstWhere('id', $groupIds[0]);

        if ($first === null || $first->item_type !== OpportunityItemType::Group) {
            return;
        }

        $parentGroupKey = $this->parentGroupKeyForPath($items, $first->parentPath());

        foreach ($groupIds as $position => $groupId) {
            $nodes = $tree->nodesAfterMovingGroup($items, $groupId, $position, $parentGroupKey);
            $tree->restructure($opportunity, $nodes);
            $opportunity = $opportunity->fresh(['items']) ?? $opportunity;
            $items = $opportunity->items;
        }

        $this->refreshOpportunity();
    }

    public function renameSection(int $groupId, string $name): void
    {
        $this->renameItem($groupId, $name);
    }

    public function deleteSection(int $groupId): void
    {
        $this->guardEditable();
        $group = $this->findGroup($groupId);
        $this->guardPricingFrozenForRemoval($group);

        $this->runMutation(function () use ($group): void {
            (new RemoveOpportunityItem)($group);

            $this->refreshOpportunity();
        }, 'Section deleted', syncTree: true);
    }

    /**
     * @return array<int, bool>
     */
    #[Computed]
    public function duplicateLineIds(): array
    {
        $items = $this->opportunity->items()->get();
        $byKey = [];

        foreach ($items as $item) {
            if ($item->itemable_id === null) {
                continue;
            }

            $key = implode('|', [
                $item->itemable_id,
                $item->itemable_type,
                $item->getRawOriginal('transaction_type'),
                $item->getRawOriginal('charge_period'),
                $item->is_optional ? 1 : 0,
                $item->parentPath() ?? 'null',
                optional($item->starts_at)->toIso8601String() ?? 'null',
                optional($item->ends_at)->toIso8601String() ?? 'null',
            ]);

            $byKey[$key][] = (int) $item->id;
        }

        $flagged = [];

        foreach ($byKey as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $flagged[$id] = true;
                }
            }
        }

        return $flagged;
    }

    public function createSection(): void
    {
        $this->guardEditable();

        $name = trim($this->newSectionName);

        if ($name === '') {
            return;
        }

        $parentId = $this->newSectionParent === '' ? null : (int) $this->newSectionParent;

        try {
            $this->runMutation(function () use ($parentId, $name): void {
                $this->addGroup($parentId, $name);
            }, 'Section created', syncTree: true, mutationMeta: ['modal' => 'create-section']);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $this->newSectionName = '';
        $this->newSectionParent = '';
    }

    /**
     * @return array{charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    #[Renderless]
    public function totalsSnapshot(): array
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;

        return $this->totalsPayload();
    }

    #[Renderless]
    public function refreshEditorContext(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $this->invalidateItemsRead();
        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);
    }

    /**
     * @return array{tree: array<int, array<string, mixed>>, revision: string, charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    public function serverTree(): array
    {
        unset($this->tree);

        return [
            'tree' => $this->tree(),
            'revision' => (string) $this->treeRevision(),
            ...$this->totalsPayload(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  array<int, int>  $pendingLocalIds
     * @return array{stale: bool, revision: string, tree: array<int, array<string, mixed>>, conflicts: array<int, string>, cache_token: string, charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    #[Renderless]
    public function pullTree(int $baseRevision, array $localRows = [], array $pendingLocalIds = []): array
    {
        $this->refreshEditorContext();

        $reconciler = app(LineItemTreeReconciler::class);
        $serverRevision = $this->treeRevision();
        $serverTree = $this->tree();
        $stale = $reconciler->isStale($baseRevision, $serverRevision);

        $tree = $serverTree;
        $conflicts = [];

        if ($localRows !== [] && ($stale || $pendingLocalIds !== [])) {
            $result = $reconciler->reconcile($localRows, $serverTree, $pendingLocalIds);
            $tree = $result['rows'];
            $conflicts = $result['conflicts'];
        }

        return [
            'stale' => $stale,
            'revision' => (string) $serverRevision,
            'tree' => $tree,
            'conflicts' => $conflicts,
            'cache_token' => $this->editorCacheToken(),
            ...$this->totalsPayload(),
        ];
    }

    public function editorCacheToken(): string
    {
        return $this->opportunity->state->value.':'.$this->opportunity->status;
    }

    /**
     * @return array{charge_total: int, deal_total: int|null, has_deal_price: bool}
     */
    private function totalsPayload(): array
    {
        return [
            'charge_total' => (int) ($this->opportunity->charge_total ?? 0),
            'deal_total' => $this->opportunity->deal_total !== null ? (int) $this->opportunity->deal_total : null,
            'has_deal_price' => $this->opportunity->deal_total !== null,
        ];
    }

    private function normalizeParentGroupKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        if (str_starts_with($key, 'section-parent:')) {
            return 'group-parent:'.substr($key, strlen('section-parent:'));
        }

        return $key;
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function parentGroupKeyForPath(Collection $items, ?string $parentPath): string
    {
        if ($parentPath === null || $parentPath === '') {
            return 'group-parent:root';
        }

        $parent = $items->first(
            fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group
                && $item->path === $parentPath,
        );

        return $parent !== null ? 'group-parent:'.$parent->id : 'group-parent:root';
    }

    private function treeService(): OpportunityEditorTreeService
    {
        return app(OpportunityEditorTreeService::class);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function resolveParentPathForAdd(
        Opportunity $opportunity,
        Collection $items,
        Product $product,
        ?string $destination,
    ): ?string {
        $tree = $this->treeService();
        $normalized = $this->normalizeGroupKey($destination);

        if ($normalized !== null && $normalized !== '' && $normalized !== 'auto:ungrouped') {
            return $tree->parentPathForGroupKey($items, $normalized);
        }

        if ($normalized === 'auto:ungrouped') {
            return null;
        }

        $stub = new OpportunityItem([
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
        ]);

        return $tree->findOrCreateAutoGroup($opportunity, $stub, $this->productCache())->path;
    }

    /**
     * Combination expansion creates its own structural parent. Keep it at the
     * root unless the operator explicitly selected an existing group.
     *
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function resolveParentPathForCombination(
        Collection $items,
        ?string $destination,
    ): ?string {
        $normalized = $this->normalizeGroupKey($destination);

        if ($normalized === null || $normalized === '' || $normalized === 'auto:ungrouped') {
            return null;
        }

        return $this->treeService()->parentPathForGroupKey($items, $normalized);
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     */
    private function resolveParentPathForText(
        Collection $items,
        ?string $destination,
    ): ?string {
        $normalized = $this->normalizeGroupKey($destination);

        if ($normalized !== null && $normalized !== '' && $normalized !== 'auto:ungrouped') {
            return $this->treeService()->parentPathForGroupKey($items, $normalized);
        }

        return null;
    }

    private function normalizeGroupKey(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (str_starts_with($key, 'section:')) {
            return 'group:'.substr($key, strlen('section:'));
        }

        return $key;
    }

    /** @return array<int, int> */
    private function duplicateIdsFor(OpportunityItem $survivor): array
    {
        return OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKeyNot($survivor->id)
            ->where('itemable_id', $survivor->itemable_id)
            ->where('itemable_type', $survivor->itemable_type)
            ->where('transaction_type', $survivor->getRawOriginal('transaction_type'))
            ->where('charge_period', $survivor->getRawOriginal('charge_period'))
            ->where('is_optional', $survivor->is_optional)
            ->when(
                $survivor->parentPath() === null,
                fn ($q) => $q->whereRaw('length(path) = 4'),
                fn ($q) => $q->where('path', 'like', $survivor->parentPath().'%')
                    ->whereRaw('length(path) = ?', [strlen((string) $survivor->parentPath()) + 4]),
            )
            ->where('starts_at', $survivor->starts_at)
            ->where('ends_at', $survivor->ends_at)
            ->whereNotNull('itemable_id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, Product> */
    private function productCache(): array
    {
        return once(function (): array {
            $productIds = $this->opportunity->items()
                ->whereNotNull('itemable_id')
                ->pluck('itemable_id')
                ->unique()
                ->all();

            if ($productIds === []) {
                return [];
            }

            return Product::query()
                ->whereIn('id', $productIds)
                ->with(['productGroup.parent', 'accessories.accessoryProduct'])
                ->get()
                ->keyBy('id')
                ->all();
        });
    }

    private function findItem(int $itemId): OpportunityItem
    {
        $item = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($itemId)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'item' => 'The line item could not be found.',
            ]);
        }

        return $item;
    }

    private function findGroup(int $groupId): OpportunityItem
    {
        $group = OpportunityItem::query()
            ->where('opportunity_id', $this->opportunity->id)
            ->whereKey($groupId)
            ->first();

        if ($group === null || $group->item_type !== OpportunityItemType::Group) {
            throw ValidationException::withMessages([
                'group' => 'The group could not be found.',
            ]);
        }

        return $group;
    }

    private function updateNameField(OpportunityItem $item, string $value): void
    {
        if (trim($value) === '' && in_array($item->item_type, [OpportunityItemType::Text, OpportunityItemType::Group], true)) {
            (new RemoveOpportunityItem)($item);

            return;
        }

        (new RenameOpportunityItem)($item, RenameOpportunityItemData::from([
            'name' => trim($value),
        ]));
    }

    private function guardEditable(): void
    {
        Gate::authorize('opportunities.edit');

        if ($this->opportunity->statusEnum()->isClosed()) {
            throw ValidationException::withMessages([
                'opportunity' => 'This opportunity is closed and its line items cannot be edited.',
            ]);
        }
    }

    private function guardFieldsEditable(string $field): void
    {
        if (! $this->opportunity->pricingFrozen()) {
            return;
        }

        $lockedFields = ['quantity', 'unit_price', 'discount_percent', 'name', 'dates', 'starts_at', 'ends_at', 'days'];

        if (in_array($field, $lockedFields, true)) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be edited while pricing is frozen.',
            ]);
        }
    }

    private function guardPricingFrozenForRemoval(OpportunityItem $item): void
    {
        if ($this->opportunity->pricingFrozen() && ! (new RemoveOpportunityItem)->canRemoveWhenPricingFrozen($item)) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be removed while pricing is frozen.',
            ]);
        }
    }

    /**
     * @param  array<int, array{id: int, depth: int}>  $nodes
     */
    private function guardPricingFrozenForTreePrune(array $nodes): void
    {
        if (! $this->opportunity->pricingFrozen()) {
            return;
        }

        $nodeIds = array_map(static fn (array $node): int => (int) $node['id'], $nodes);
        $existingIds = ($this->opportunity->fresh(['items']) ?? $this->opportunity)
            ->items
            ->pluck('id')
            ->all();

        if (collect($existingIds)->diff($nodeIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'opportunity' => 'Line items cannot be removed while pricing is frozen.',
            ]);
        }
    }

    private function refreshOpportunity(): void
    {
        $this->opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $this->fieldsEditable = $this->editable && ! $this->opportunity->pricingFrozen();

        $this->invalidateItemsRead();
        unset($this->tree, $this->destinations, $this->sectionOptions, $this->parentGroupOptions, $this->duplicateLineIds);

        $this->dispatch(
            'opportunity-totals-updated',
            chargeTotalMinor: (int) ($this->opportunity->charge_total ?? 0),
        );
    }

    /**
     * @return array{0: Opportunity, 1: EloquentCollection<int, OpportunityItem>}
     */
    private function sharedItemsRead(): array
    {
        if ($this->itemsReadOpportunity !== null && $this->itemsRead !== null) {
            return [$this->itemsReadOpportunity, $this->itemsRead];
        }

        $opportunity = $this->opportunity->fresh() ?? $this->opportunity;
        $opportunity->load([
            'store',
            'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
            'items.item',
            'items.dispatchStore',
        ]);
        $opportunity->items->each(
            fn (OpportunityItem $item): OpportunityItem => $item->setRelation('opportunity', $opportunity),
        );

        $this->itemsReadOpportunity = $opportunity;
        $this->itemsRead = $opportunity->items;

        return [$this->itemsReadOpportunity, $this->itemsRead];
    }

    /**
     * Resolve item contexts once at the nested line-items read boundary and
     * pass the already-loaded Product rows explicitly to downstream readers.
     * Missing prepared inputs must not trigger hidden Product I/O.
     *
     * @param  EloquentCollection<int, OpportunityItem>  $items
     * @return array{0: array<int, OpportunityItemReadContext>, 1: PreparedProductInputs, 2: PreparedStockInputs, 3: PreparedStandardDemandInputs}
     */
    private function prepareLineItemsRead(Opportunity $opportunity, EloquentCollection $items): array
    {
        $resolver = app(OpportunityItemDemandResolver::class);
        $contexts = [];
        $productIds = $items
            ->filter(static fn (OpportunityItem $item): bool => $item->isProductBacked())
            ->pluck('itemable_id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $item->setRelation('opportunity', $opportunity);
            $preparedProduct = $item->isProductBacked()
                ? $products->get((int) $item->itemable_id)
                : null;
            $item->setRelation('item', $preparedProduct);
            $contexts[$item->id] = OpportunityItemReadContext::fromResolved(
                (int) $item->id,
                $resolver->resolveContext($item, $preparedProduct),
            );

            if ($preparedProduct !== null) {
                $products->put($preparedProduct->id, $preparedProduct);
            }
        }

        $preparedProducts = PreparedProductInputs::fromCollection($products);
        $stockByKey = [];
        $pipeline = app(RecalculationPipeline::class);

        foreach ($contexts as $context) {
            if ($context->productId === null || $context->storeId === null) {
                continue;
            }

            $key = PreparedStockInputs::key($context->productId, $context->storeId);

            if (array_key_exists($key, $stockByKey)) {
                continue;
            }

            $stockByKey[$key] = $pipeline->totalStock(
                $preparedProducts->required($context->productId),
                $context->storeId,
            );
        }

        $demandByKey = [];

        foreach ($contexts as $context) {
            if ($context->productId === null || $context->storeId === null) {
                continue;
            }

            $key = PreparedStandardDemandInputs::key(
                $context->productId,
                $context->storeId,
                $context->from,
                $context->to,
            );

            if (array_key_exists($key, $demandByKey)) {
                continue;
            }

            $demandByKey[$key] = Demand::query()
                ->where('product_id', $context->productId)
                ->where('store_id', $context->storeId)
                ->active()
                ->overlapping($context->from, $context->to)
                ->get();
        }

        return [
            $contexts,
            $preparedProducts,
            new PreparedStockInputs($stockByKey),
            new PreparedStandardDemandInputs($demandByKey),
        ];
    }

    private function invalidateItemsRead(): void
    {
        $this->itemsReadOpportunity = null;
        $this->itemsRead = null;
    }

    /**
     * @param  array{modal?: string|null, modalId?: string|null}  $mutationMeta
     */
    private function runMutation(
        callable $callback,
        ?string $successMessage = null,
        bool $syncTree = false,
        array $mutationMeta = [],
    ): void {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $this->firstMessageFrom($e));

            throw $e;
        }

        if ($syncTree) {
            $this->dispatchTreeMutationDone($mutationMeta);
        }

        if ($successMessage !== null) {
            $this->dispatch('toast', type: 'success', message: $successMessage);
        }
    }

    /**
     * Notify the Alpine table (wire:ignore) to pull fresh server rows and optionally
     * close a modal. Uses a browser event handled in JS so close-modal is always
     * dispatched via Alpine ($dispatch), matching x-signals.modal listeners.
     *
     * @param  array{modal?: string|null, modalId?: string|null}  $meta
     */
    private function dispatchTreeMutationDone(array $meta = []): void
    {
        $this->dispatch(
            'line-items-mutation-done',
            modal: $meta['modal'] ?? null,
            modalId: $meta['modalId'] ?? null,
        );
    }

    private function changeItemDaysAndReturn(OpportunityItem $item, int $days): true
    {
        $this->changeItemDays($item, $days);

        return true;
    }

    private function changeItemDays(OpportunityItem $item, int $days): void
    {
        $days = max(1, $days);
        $opportunity = $item->opportunity()->firstOrFail();

        $startSource = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now('UTC');
        $start = Carbon::parse($startSource)->startOfDay();
        $end = $start->copy()->addDays($days);

        (new ChangeItemDates)($item, ChangeItemDatesData::from([
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
        ]));
    }

    private function firstMessageFrom(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            foreach ((array) $messages as $message) {
                return (string) $message;
            }
        }

        return $e->getMessage();
    }

    public function render(): View
    {
        return view('project-opportunities::line-items');
    }
}

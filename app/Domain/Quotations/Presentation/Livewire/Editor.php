<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Livewire;

use App\Domain\Pricing\Enums\LineItemTransactionType;
use App\Domain\Pricing\Services\RoundingStrategy;
use App\Domain\Products\Enums\ProductType;
use App\Domain\Products\Models\Product;
use App\Domain\Quotations\Actions\AcceptQuotationVersion;
use App\Domain\Quotations\Actions\ActivateQuotationVersion;
use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\ClearQuotationDealPrice;
use App\Domain\Quotations\Actions\CreateQuotationVersion;
use App\Domain\Quotations\Actions\DeclineQuotationVersion;
use App\Domain\Quotations\Actions\ExpandQuotationProduct;
use App\Domain\Quotations\Actions\RecalculateQuotationVersion;
use App\Domain\Quotations\Actions\RemoveQuotationItem;
use App\Domain\Quotations\Actions\RestructureQuotationItems;
use App\Domain\Quotations\Actions\SendQuotationVersion;
use App\Domain\Quotations\Actions\SetQuotationDealPrice;
use App\Domain\Quotations\Actions\SubstituteQuotationItem;
use App\Domain\Quotations\Actions\UpdateQuotationItem;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\CreateQuotationVersionData;
use App\Domain\Quotations\Data\DeclineQuotationVersionData;
use App\Domain\Quotations\Data\RestructureQuotationItemsData;
use App\Domain\Quotations\Data\SendQuotationVersionData;
use App\Domain\Quotations\Data\SetQuotationDealPriceData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Enums\QuotationItemType;
use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Enums\QuotationVersionType;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditorReadModel;
use App\Domain\Quotations\Services\QuotationProductCatalogue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Editor extends Component
{
    public Quotation $quotation;

    public string $quickAddDestination = '';

    public string $newSectionName = '';

    public string $newSectionParent = '';

    public string $dealPriceInput = '';

    public string $dealPriceMode = 'flat_headline';

    public bool $editable = false;

    public bool $fieldsEditable = false;

    /** @var list<int> */
    public array $collapsedSectionIds = [];

    public function mount(Quotation $quotation): void
    {
        Gate::authorize('view', $quotation);
        $this->quotation = $quotation;
        $this->dealPriceInput = $quotation->deal_total !== null ? $quotation->formatMoneyCost('deal_total') : '';
        $this->dealPriceMode = $quotation->deal_price_mode->value;
        $this->refreshEditability();
    }

    public function rendering(View $view): void
    {
        $view->title($this->quotation->number.' — '.$this->quotation->subject);
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function tree(): array
    {
        return app(QuotationEditorReadModel::class)->tree($this->quotation, $this->activeVersion());
    }

    #[Renderless]
    public function treeRevisionToken(): string
    {
        return (string) $this->quotation->refresh()->editor_revision;
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function destinations(): array
    {
        $options = [['value' => '', 'label' => '— Root —']];

        foreach ($this->activeVersion()->items()->where('item_type', QuotationItemType::Group)->orderBy('path')->get() as $group) {
            $options[] = ['value' => (string) $group->id, 'label' => 'Section · '.$group->name];
        }

        return $options;
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function sectionOptions(): array
    {
        return $this->destinations();
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function searchProducts(string $query): array
    {
        Gate::authorize('products.view');

        return app(QuotationProductCatalogue::class)->search($this->quotation, $query);
    }

    /** @return list<array<string, mixed>> */
    #[Renderless]
    public function catalogueIndex(): array
    {
        Gate::authorize('products.view');

        return app(QuotationProductCatalogue::class)->index($this->quotation);
    }

    public function addProduct(int $productId, float $quantity = 1, ?string $destination = null): void
    {
        $this->guardEditable();
        $product = Product::query()->findOrFail($productId);
        $itemType = match ($product->product_type) {
            ProductType::Service => QuotationItemType::Service,
            default => QuotationItemType::Product,
        };
        $transactionType = match ($product->product_type) {
            ProductType::Sale => LineItemTransactionType::Sale,
            ProductType::Service => LineItemTransactionType::Service,
            default => LineItemTransactionType::Rental,
        };

        $created = app(AddQuotationItem::class)($this->quotation, $this->activeVersion(), AddQuotationItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => $itemType->value,
            'quantity' => (string) max(0.01, $quantity),
            'transaction_type' => $transactionType->value,
            'parent_path' => $this->groupPath($destination),
        ]));
        $principal = QuotationItem::query()->findOrFail($created->id);

        if ($product->components()->exists() || $product->accessories()->where('included', true)->exists()) {
            app(ExpandQuotationProduct::class)($this->quotation, $this->activeVersion(), $principal);
        }
        $this->mutationDone();
    }

    public function applyDealPrice(): void
    {
        $this->validate([
            'dealPriceInput' => ['required', 'numeric', 'min:0'],
            'dealPriceMode' => ['required', 'in:flat_headline,proportional'],
        ]);
        $minor = app(RoundingStrategy::class)->roundMajorToMinorUnits($this->dealPriceInput, $this->quotation->currency_code);
        app(SetQuotationDealPrice::class)(
            $this->quotation,
            $this->activeVersion(),
            SetQuotationDealPriceData::from(['deal_total' => $minor, 'deal_price_mode' => $this->dealPriceMode]),
        );
        $this->reloadPage();
    }

    public function clearDealPrice(): void
    {
        app(ClearQuotationDealPrice::class)($this->quotation, $this->activeVersion());
        $this->reloadPage();
    }

    public function sendActiveVersion(): void
    {
        app(SendQuotationVersion::class)(
            $this->quotation,
            $this->activeVersion(),
            SendQuotationVersionData::from([]),
        );
        $this->reloadPage();
    }

    public function acceptActiveVersion(): void
    {
        app(AcceptQuotationVersion::class)(
            $this->quotation,
            $this->activeVersion(),
            AcceptQuotationVersionData::from([]),
        );
        $this->reloadPage();
    }

    public function declineActiveVersion(): void
    {
        app(DeclineQuotationVersion::class)(
            $this->quotation,
            $this->activeVersion(),
            DeclineQuotationVersionData::from([]),
        );
        $this->reloadPage();
    }

    public function createVersion(string $type): void
    {
        app(CreateQuotationVersion::class)(
            $this->quotation,
            CreateQuotationVersionData::from(['version_type' => QuotationVersionType::from($type)->value]),
        );
        $this->reloadPage();
    }

    public function activateVersion(int $versionId): void
    {
        app(ActivateQuotationVersion::class)(
            $this->quotation,
            $this->quotation->versions()->whereKey($versionId)->firstOrFail(),
        );
        $this->reloadPage();
    }

    public function addGroup(?int $parentGroupId = null, string $name = ''): int
    {
        $this->guardEditable();
        $created = app(AddQuotationItem::class)($this->quotation, $this->activeVersion(), AddQuotationItemData::from([
            'name' => trim($name) !== '' ? trim($name) : 'New section',
            'item_type' => QuotationItemType::Group->value,
            'parent_path' => $this->groupPath($parentGroupId !== null ? (string) $parentGroupId : null),
        ]));
        $this->mutationDone();

        return $created->id;
    }

    public function createSection(): void
    {
        $name = trim($this->newSectionName);

        if ($name === '') {
            $this->addError('newSectionName', 'Enter a section name.');

            return;
        }

        $this->addGroup($this->newSectionParent !== '' ? (int) $this->newSectionParent : null, $name);
        $this->newSectionName = '';
        $this->newSectionParent = '';
        $this->mutationDone(['modalId' => 'create-section']);
    }

    public function renameItem(int $itemId, string $name): void
    {
        app(UpdateQuotationItem::class)($this->findItem($itemId), UpdateQuotationItemData::from(['name' => $name]));
        $this->mutationDone(['modalId' => 'rename-section']);
    }

    public function saveLineEdits(
        int $itemId,
        ?string $unitPrice,
        ?string $discount,
        ?string $startsAt,
        ?string $endsAt,
        ?string $description,
        ?string $notes,
    ): void {
        $payload = [
            'unit_price' => $unitPrice !== null ? app(RoundingStrategy::class)->roundMajorToMinorUnits($unitPrice, $this->quotation->currency_code) : null,
            'discount_percent' => $discount,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'description' => $description,
            'notes' => $notes,
        ];
        app(UpdateQuotationItem::class)($this->findItem($itemId), UpdateQuotationItemData::from($payload));
        $this->mutationDone(['modalId' => 'edit-line']);
    }

    /** @return array<string, mixed> */
    public function addInlineSectionSnapshot(): array
    {
        $id = $this->addGroup();

        return $this->snapshotFor($id);
    }

    /** @return array<string, mixed> */
    public function addInlineTextLineSnapshot(?string $destination = null): array
    {
        $this->guardEditable();
        $created = app(AddQuotationItem::class)($this->quotation, $this->activeVersion(), AddQuotationItemData::from([
            'name' => 'Free text item',
            'item_type' => QuotationItemType::Text->value,
            'parent_path' => $this->groupPath($destination),
        ]));
        $this->mutationDone();

        return $this->snapshotFor($created->id);
    }

    /**
     * @param  list<array{id: int, field: string, value: mixed}>  $mutations
     * @return array<string, mixed>
     */
    public function applyFieldMutations(array $mutations): array
    {
        $this->guardEditable();

        foreach ($mutations as $mutation) {
            $item = $this->findItem($mutation['id']);
            $field = $mutation['field'];
            $value = $mutation['value'];
            $payload = match ($field) {
                'name' => ['name' => (string) $value],
                'quantity' => ['quantity' => (string) $value],
                'unit_price' => ['unit_price' => app(RoundingStrategy::class)->roundMajorToMinorUnits((string) $value, $this->quotation->currency_code)],
                'discount_percent' => ['discount_percent' => $value === '' ? null : (string) $value],
                'starts_at' => ['starts_at' => $value ?: null],
                'ends_at' => ['ends_at' => $value ?: null],
                'notes' => ['notes' => $value ?: null],
                default => throw ValidationException::withMessages(['field' => "The {$field} field is not editable."]),
            };
            app(UpdateQuotationItem::class)($item, UpdateQuotationItemData::from($payload));
        }

        $this->mutationDone();

        return $this->totalsSnapshot();
    }

    public function toggleOptional(int $itemId): void
    {
        $item = $this->findItem($itemId);
        app(UpdateQuotationItem::class)($item, UpdateQuotationItemData::from(['is_optional' => ! $item->is_optional]));
        $this->mutationDone();
    }

    public function resetItemDiscount(int $itemId): void
    {
        app(UpdateQuotationItem::class)($this->findItem($itemId), UpdateQuotationItemData::from(['discount_percent' => null]));
        $this->mutationDone();
    }

    public function substituteItem(int $itemId, int $productId): void
    {
        app(SubstituteQuotationItem::class)(
            $this->findItem($itemId),
            Product::query()->findOrFail($productId),
        );
        $this->mutationDone();
    }

    /**
     * @param  list<array{id: int, depth: int}>  $nodes
     * @return array<string, mixed>
     */
    public function persistTree(array $nodes, int|string $baseRevision = 0): array
    {
        $this->guardEditable();
        $currentRevision = $this->quotation->refresh()->editor_revision;

        if ((int) $baseRevision !== $currentRevision) {
            return [
                'stale' => true,
                'revision' => $currentRevision,
                'tree' => app(QuotationEditorReadModel::class)->tree($this->quotation, $this->activeVersion()),
            ];
        }

        app(RestructureQuotationItems::class)(
            $this->quotation,
            $this->activeVersion(),
            RestructureQuotationItemsData::from(['nodes' => $nodes]),
        );
        $this->mutationDone();

        return ['stale' => false, ...$this->totalsSnapshot()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $localRows
     * @param  list<int>  $pendingIds
     * @return array<string, mixed>
     */
    #[Renderless]
    public function pullTree(int|string $baseRevision = 0, array $localRows = [], array $pendingIds = [], bool $forceTree = false): array
    {
        $revision = $this->quotation->refresh()->editor_revision;
        $changed = $forceTree || (int) $baseRevision !== $revision;

        return [
            ...$this->totalsSnapshot(),
            'changed' => $changed,
            'stale' => (int) $baseRevision !== 0 && (int) $baseRevision !== $revision,
            'tree' => $changed ? app(QuotationEditorReadModel::class)->tree($this->quotation, $this->activeVersion()) : null,
            'collapsed_section_ids' => $this->collapsedSectionIds,
            'conflicts' => [],
        ];
    }

    /** @return array<string, mixed> */
    #[Renderless]
    public function totalsSnapshot(): array
    {
        $totals = app(QuotationEditorReadModel::class)->totals($this->quotation->refresh(), $this->activeVersion());

        return [
            'revision' => $totals['revision'],
            'charge_total' => $totals['net'],
            'tax_total' => $totals['tax'],
            'gross_total' => $totals['gross'],
            'has_deal_price' => $this->quotation->deal_total !== null,
            'deal_total' => $this->quotation->deal_total,
        ];
    }

    public function recalculatePricing(bool $resetPrices = false, bool $resetDiscounts = false): void
    {
        $this->guardEditable();
        app(RecalculateQuotationVersion::class)($this->quotation, $this->activeVersion());
        $this->mutationDone();
    }

    /** @param list<int|string> $ids */
    public function setCollapsedSectionIds(array $ids): void
    {
        $this->collapsedSectionIds = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    public function removeItem(int $itemId): void
    {
        app(RemoveQuotationItem::class)($this->findItem($itemId));
        $this->mutationDone();
    }

    public function mergeDuplicates(int $survivorId): void
    {
        $survivor = $this->findItem($survivorId);
        $duplicates = $this->activeVersion()->items()
            ->where('id', '!=', $survivor->id)
            ->where('itemable_id', $survivor->itemable_id)
            ->where('itemable_type', $survivor->itemable_type)
            ->where('transaction_type', $survivor->getRawOriginal('transaction_type'))
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $quantity = $duplicates->reduce(
            fn (string $total, QuotationItem $item): string => bcadd($total, (string) $item->quantity, 2),
            (string) $survivor->quantity,
        );
        app(UpdateQuotationItem::class)($survivor, UpdateQuotationItemData::from(['quantity' => $quantity]));

        foreach ($duplicates as $duplicate) {
            app(RemoveQuotationItem::class)($duplicate);
        }

        $this->mutationDone();
    }

    public function assignToGroup(int $itemId, ?int $groupId): void
    {
        $this->guardEditable();
        $items = $this->activeVersion()->items()->orderBy('path')->get()->values();
        $item = $items->firstWhere('id', $itemId);

        if (! $item instanceof QuotationItem) {
            throw ValidationException::withMessages(['item_id' => 'The quotation item no longer exists.']);
        }

        $without = $items->reject(fn (QuotationItem $candidate): bool => $candidate->id === $item->id)->values();
        $insertAt = $without->count();
        $targetDepth = 1;

        if ($groupId !== null) {
            $groupIndex = $without->search(fn (QuotationItem $candidate): bool => $candidate->id === $groupId);
            if ($groupIndex === false || $without[$groupIndex]->item_type !== QuotationItemType::Group) {
                throw ValidationException::withMessages(['group_id' => 'Choose a valid quotation section.']);
            }

            $insertAt = $groupIndex + 1;
            while ($insertAt < $without->count() && $without[$insertAt]->depth() > $without[$groupIndex]->depth()) {
                $insertAt++;
            }
            $targetDepth = $without[$groupIndex]->depth() + 1;
        }

        $without->splice($insertAt, 0, [$item]);
        $nodes = $without->map(fn (QuotationItem $candidate): array => [
            'id' => $candidate->id,
            'depth' => $candidate->id === $item->id ? $targetDepth : $candidate->depth(),
        ])->all();
        app(RestructureQuotationItems::class)($this->quotation, $this->activeVersion(), RestructureQuotationItemsData::from(['nodes' => $nodes]));
        $this->mutationDone();
    }

    public function render(): View
    {
        return view('quotations::editor', [
            'activeVersion' => $this->activeVersion(),
            'versions' => $this->quotation->versions()->orderByDesc('version_number')->get(),
            'tree' => $this->tree(),
            'destinations' => $this->destinations(),
        ]);
    }

    private function activeVersion(): QuotationVersion
    {
        return QuotationVersion::query()->findOrFail($this->quotation->refresh()->active_version_id);
    }

    private function findItem(int $itemId): QuotationItem
    {
        return $this->activeVersion()->items()->whereKey($itemId)->firstOrFail();
    }

    private function groupPath(?string $groupId): ?string
    {
        if ($groupId === null || $groupId === '') {
            return null;
        }

        return $this->activeVersion()->items()
            ->whereKey((int) $groupId)
            ->where('item_type', QuotationItemType::Group)
            ->value('path');
    }

    /** @return array<string, mixed> */
    private function snapshotFor(int $itemId): array
    {
        $row = collect(app(QuotationEditorReadModel::class)->tree($this->quotation, $this->activeVersion()))
            ->firstWhere('id', $itemId);

        return ['id' => $itemId, 'row' => $row, ...$this->totalsSnapshot()];
    }

    private function refreshEditability(): void
    {
        $version = $this->activeVersion();
        $this->editable = Gate::allows('update', $this->quotation)
            && ! $this->quotation->status->isTerminal()
            && $version->status === QuotationVersionStatus::Draft;
        $this->fieldsEditable = $this->editable;
    }

    private function guardEditable(): void
    {
        $this->refreshEditability();

        if (! $this->editable) {
            throw ValidationException::withMessages(['quotation' => 'Create or activate a draft version before editing.']);
        }
    }

    /** @param array<string, mixed> $meta */
    private function mutationDone(array $meta = []): void
    {
        $this->quotation->refresh();
        unset($this->tree, $this->destinations, $this->sectionOptions);
        $this->dispatch('line-items-mutation-done', ...$meta);
    }

    private function reloadPage(): void
    {
        $this->redirectRoute('quotations.edit', ['quotation' => $this->quotation->id], navigate: true);
    }
}

<?php

use App\Actions\Products\CreateProduct;
use App\Actions\Products\UpdateProduct;
use App\Data\Products\CreateProductData;
use App\Data\Products\UpdateProductData;
use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Livewire\Concerns\LoadsCustomFieldValues;
use App\Livewire\Concerns\ReKeysCustomFieldErrors;
use App\Models\CostGroup;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTaxClass;
use App\Models\RevenueGroup;
use App\Models\TaxCategory;
use App\Services\Api\RansackFilter;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    use LoadsCustomFieldValues;
    use ReKeysCustomFieldErrors;

    public ?int $productId = null;

    public string $name = '';

    public string $productType = 'rental';

    public bool $isActive = true;

    public string $description = '';

    public ?int $productGroupId = null;

    public string $productGroupSearch = '';

    public int $allowedStockType = 1;

    public int $stockMethod = 1;

    public ?string $weight = null;

    public ?string $barcode = null;

    public ?string $sku = null;

    public string $replacementCharge = '0.00';

    public string $bufferPercent = '0.0';

    public int $postRentUnavailability = 0;

    public bool $accessoryOnly = false;

    public bool $system = false;

    public bool $discountable = true;

    public ?int $taxClassId = null;

    public ?int $purchaseTaxClassId = null;

    public ?int $saleTaxCategoryId = null;

    public ?int $purchaseTaxCategoryId = null;

    public ?int $rentalRevenueGroupId = null;

    public ?int $saleRevenueGroupId = null;

    public ?int $subRentalCostGroupId = null;

    public string $subRentalPrice = '0.00';

    public ?int $purchaseCostGroupId = null;

    public string $purchasePrice = '0.00';

    public ?int $countryOfOriginId = null;

    public string $countrySearch = '';

    /** @var list<string> */
    public array $tags = [];

    /** @var array<string, mixed> */
    public array $customFieldValues = [];

    public function mount(?Product $product = null): void
    {
        // Pre-populate product type from ?type= query param
        $type = request()->query('type');
        if (is_string($type) && in_array($type, array_column(ProductType::cases(), 'value'))) {
            $this->productType = $type;
        }

        // Force bulk stock method for Sale products
        if ($this->productType === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }

        if ($product?->exists) {
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->productType = $product->product_type->value;
            $this->isActive = $product->is_active;
            $this->description = $product->description ?? '';
            $this->productGroupId = $product->product_group_id;
            $this->allowedStockType = $product->allowed_stock_type?->value ?? 1;
            $this->stockMethod = $product->stock_method?->value ?? StockMethod::Bulk->value;
            $this->weight = $product->weight;
            $this->barcode = $product->barcode;
            $this->sku = $product->sku;
            $this->replacementCharge = $this->majorAmount($product->replacement_charge ?? 0);
            $this->bufferPercent = $product->buffer_percent ?? '0.0';
            $this->postRentUnavailability = $product->post_rent_unavailability ?? 0;
            $this->accessoryOnly = $product->accessory_only ?? false;
            $this->system = $product->system ?? false;
            $this->discountable = $product->discountable ?? true;
            $this->taxClassId = $product->tax_class_id;
            $this->purchaseTaxClassId = $product->purchase_tax_class_id;
            $this->saleTaxCategoryId = $product->sale_tax_category_id;
            $this->purchaseTaxCategoryId = $product->purchase_tax_category_id;
            $this->rentalRevenueGroupId = $product->rental_revenue_group_id;
            $this->saleRevenueGroupId = $product->sale_revenue_group_id;
            $this->subRentalCostGroupId = $product->sub_rental_cost_group_id;
            $this->subRentalPrice = $this->majorAmount($product->sub_rental_price ?? 0);
            $this->purchaseCostGroupId = $product->purchase_cost_group_id;
            $this->purchasePrice = $this->majorAmount($product->purchase_price ?? 0);
            $this->countryOfOriginId = $product->country_of_origin_id;
            $this->tags = $product->tag_list ?? [];

            // Force bulk stock method for Sale products
            if ($this->productType === 'sale') {
                $this->stockMethod = StockMethod::Bulk->value;
            }

            // Load existing custom field values
            $this->customFieldValues = $this->loadCustomFieldValuesFrom($product);
        }
    }

    /**
     * When product type changes, enforce Sale = Bulk stock method.
     */
    public function updatedProductType(string $value): void
    {
        if ($value === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }
    }

    /**
     * Prevent Serialised stock method for Sale products.
     */
    public function updatedStockMethod(): void
    {
        if ($this->productType === 'sale') {
            $this->stockMethod = StockMethod::Bulk->value;
        }
    }

    /**
     * Select a product group from the autocomplete results.
     */
    public function selectProductGroup(int $id): void
    {
        $this->productGroupId = $id;
        $this->productGroupSearch = '';
    }

    /**
     * Select a country from the autocomplete results.
     */
    public function selectCountry(int $id): void
    {
        $this->countryOfOriginId = $id;
        $this->countrySearch = '';
    }

    /**
     * @return Collection<int, ProductGroup>
     */
    #[Computed]
    public function productGroupResults(): Collection
    {
        if (strlen($this->productGroupSearch) < 1) {
            return new Collection;
        }

        return ProductGroup::query()
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->productGroupSearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Country>
     */
    #[Computed]
    public function countryResults(): Collection
    {
        if (strlen($this->countrySearch) < 1) {
            return new Collection;
        }

        return Country::query()
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($this->countrySearch).'%')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);
    }

    /**
     * Get the selected product group name for display.
     */
    #[Computed]
    public function selectedProductGroupName(): ?string
    {
        if (! $this->productGroupId) {
            return null;
        }

        return ProductGroup::query()->where('id', $this->productGroupId)->value('name');
    }

    /**
     * Get the selected country name for display.
     */
    #[Computed]
    public function selectedCountryName(): ?string
    {
        if (! $this->countryOfOriginId) {
            return null;
        }

        return Country::query()->where('id', $this->countryOfOriginId)->value('name');
    }

    private function moneyCurrency(): string
    {
        $currency = settings('company.base_currency');

        return is_string($currency) && $currency !== '' ? $currency : 'GBP';
    }

    private function majorAmount(int $minorAmount): string
    {
        return (string) Money::ofMinor($minorAmount, $this->moneyCurrency())->getAmount();
    }

    private function minorAmount(string|int|float|null $majorAmount): int
    {
        $value = $majorAmount === null || $majorAmount === '' ? '0' : (string) $majorAmount;

        return Money::of($value, $this->moneyCurrency(), roundingMode: RoundingMode::HALF_UP)
            ->getMinorAmount()
            ->toInt();
    }

    public function save(): void
    {
        $nameUniqueRule = Rule::unique('products', 'name');

        if ($this->productId) {
            $nameUniqueRule->ignore($this->productId);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255', $nameUniqueRule],
            'replacementCharge' => ['required', 'numeric', 'min:0'],
            'subRentalPrice' => ['required', 'numeric', 'min:0'],
            'purchasePrice' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = [
            'name' => $this->name,
            'product_type' => $this->productType,
            'is_active' => $this->isActive,
            'description' => $this->description ?: null,
            'product_group_id' => $this->productGroupId,
            'allowed_stock_type' => $this->allowedStockType,
            'stock_method' => $this->stockMethod,
            'weight' => $this->weight,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'replacement_charge' => $this->minorAmount($this->replacementCharge),
            'buffer_percent' => $this->bufferPercent,
            'post_rent_unavailability' => $this->postRentUnavailability,
            'accessory_only' => $this->accessoryOnly,
            'system' => $this->system,
            'discountable' => $this->discountable,
            'tax_class_id' => $this->taxClassId,
            'purchase_tax_class_id' => $this->purchaseTaxClassId,
            'sale_tax_category_id' => $this->saleTaxCategoryId,
            'purchase_tax_category_id' => $this->purchaseTaxCategoryId,
            'rental_revenue_group_id' => $this->rentalRevenueGroupId,
            'sale_revenue_group_id' => $this->saleRevenueGroupId,
            'sub_rental_cost_group_id' => $this->subRentalCostGroupId,
            'sub_rental_price' => $this->minorAmount($this->subRentalPrice),
            'purchase_cost_group_id' => $this->purchaseCostGroupId,
            'purchase_price' => $this->minorAmount($this->purchasePrice),
            'country_of_origin_id' => $this->countryOfOriginId,
            'tag_list' => $this->tags !== [] ? $this->tags : null,
            'custom_fields' => $this->customFieldValues,
        ];

        if (! Gate::check('costs.view')) {
            unset($payload['purchase_price']);
        }

        try {
            if ($this->productId) {
                $product = Product::findOrFail($this->productId);
                $result = (new UpdateProduct)($product, UpdateProductData::from($payload));
            } else {
                $result = (new CreateProduct)(CreateProductData::from($payload));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->reKeyCustomFieldErrors($e, $this->referenceData['customFields']->pluck('name')->all());
        }

        $this->redirect(route('products.show', $result->id), navigate: true);
    }

    #[Computed]
    public function referenceData(): array
    {
        return [
            'taxClasses' => ProductTaxClass::query()->orderBy('name')->get(),
            'taxCategories' => TaxCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'revenueGroups' => RevenueGroup::query()->orderBy('name')->get(['id', 'name']),
            'costGroups' => CostGroup::query()->orderBy('name')->get(['id', 'name']),
            'customFields' => CustomField::query()
                ->forModule('Product')
                ->active()
                ->with(['group', 'listName.values'])
                ->orderBy('sort_order')
                ->get(),
        ];
    }

    /**
     * Distinct existing product tags, used as autocomplete suggestions.
     *
     * @return list<string>
     */
    public function tagSuggestions(): array
    {
        return Product::query()
            ->whereNotNull('tag_list')
            ->pluck('tag_list')
            ->flatMap(fn ($tags): array => is_array($tags) ? $tags : [])
            ->filter(fn ($tag): bool => is_string($tag) && $tag !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $isEditing = $this->productId !== null;
        $ref = $this->referenceData;

        return [
            'isEditing' => $isEditing,
            'tagSuggestions' => $this->tagSuggestions(),
            'product' => $isEditing ? Product::find($this->productId)?->loadCount(['stockLevels', 'accessories', 'attachments']) : null,
            'productTypes' => ProductType::cases(),
            'stockMethods' => StockMethod::cases(),
            'taxClasses' => $ref['taxClasses'],
            'taxCategories' => $ref['taxCategories'],
            'revenueGroups' => $ref['revenueGroups'],
            'costGroups' => $ref['costGroups'],
            'customFields' => $ref['customFields'],
            'groupedCustomFields' => $ref['customFields']->groupBy(fn ($f) => $f->group?->name ?? 'General'),
        ];
    }
}; ?>

<section class="w-full">
    @if($isEditing && $product)
        @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Edit'])
        @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => ''])
    @else
        <x-dense::page-header title="Create Product">
            <x-slot:breadcrumbs>
                <a href="{{ route('products.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Products</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <form wire:submit="save">
            <div
                x-data="{
                    selectedId: null,
                    init() {
                        this.$nextTick(() => this.select(this.$id('tab', 1)))
                    },
                    select(id) {
                        this.selectedId = id
                    },
                    isSelected(id) {
                        return this.selectedId === id
                    },
                    whichChild(el, parent) {
                        return Array.from(parent.children).indexOf(el) + 1
                    }
                }"
                x-id="['tab']"
                class="space-y-6"
            >
                    <nav
                        x-ref="tablist"
                    @keydown.right.prevent.stop="$focus.wrap().next()"
                    @keydown.home.prevent.stop="$focus.first()"
                    @keydown.page-up.prevent.stop="$focus.first()"
                    @keydown.left.prevent.stop="$focus.wrap().prev()"
                    @keydown.end.prevent.stop="$focus.last()"
                    @keydown.page-down.prevent.stop="$focus.last()"
                        role="tablist"
                        class="app-subnav -mx-6 max-md:-mx-5 max-sm:-mx-3 px-6 max-md:px-5 max-sm:px-3"
                    >
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >General</button>
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >Stock</button>
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >Identification</button>
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >Commercial</button>
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >Additional</button>
                        <button
                            :id="$id('tab', whichChild($el, $refs.tablist))"
                            @click="select($el.id)"
                            @mousedown.prevent
                            @focus="select($el.id)"
                            type="button"
                            :tabindex="isSelected($el.id) ? 0 : -1"
                            :aria-selected="isSelected($el.id)"
                            :class="isSelected($el.id) ? 'active' : ''"
                            class="subnav-link"
                            role="tab"
                        >Custom Fields</button>
                    </nav>

                <div role="tabpanels">
                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >

                {{-- ======================================== --}}
                {{-- LEFT COLUMN — Core fields --}}
                {{-- ======================================== --}}
                <div class="space-y-6">

                    {{-- Icon Upload (edit mode only) --}}
                    @if($isEditing && $product)
                        <x-signals.form-section title="Product Image">
                            <livewire:components.icon-upload :model="$product" :key="'icon-'.$product->id" />
                        </x-signals.form-section>
                    @endif

                    {{-- Basic Info --}}
                    <x-signals.form-section title="Basic Info">
                        <div class="space-y-3">
                            <flux:input wire:model="name" label="Name" required />

                            <flux:textarea wire:model="description" label="Description" rows="2" />

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model.live="productType" label="Product Type" required>
                                    @foreach($productTypes as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </flux:select>

                                {{-- Product Group autocomplete --}}
                                <div>
                                    <label class="block text-sm font-medium mb-1">Product Group</label>
                                    <div x-data="{ open: false }" class="relative">
                                        <div class="flex items-center gap-1">
                                            @if($this->productGroupId)
                                                <x-signals.autocomplete-selected :label="$this->selectedProductGroupName">
                                                    <button type="button" wire:click="$set('productGroupId', null)" class="shrink-0 text-zinc-400 hover:text-zinc-700 dark:hover:text-white">&times;</button>
                                                </x-signals.autocomplete-selected>
                                            @else
                                                <flux:input wire:model.live.debounce.300ms="productGroupSearch" placeholder="Search product groups..." x-on:focus="open = true" x-on:input="open = true" />
                                            @endif
                                        </div>
                                        @if(!$this->productGroupId && count($this->productGroupResults) > 0)
                                            <div x-show="open" x-on:click.outside="open = false" class="s-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 50; margin-top: 4px;">
                                                @foreach($this->productGroupResults as $group)
                                                    <button type="button" wire:click="selectProductGroup({{ $group->id }})" x-on:click="open = false" class="s-dropdown-item w-full text-left">{{ $group->name }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </x-signals.form-section>

                        </div>
                    </section>

                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >
                        <x-signals.form-section title="Stock">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <div>
                                    @if($productType === 'sale')
                                        <flux:select wire:model.live="stockMethod" label="Stock Method" disabled>
                                            <option value="{{ \App\Enums\StockMethod::Bulk->value }}">{{ \App\Enums\StockMethod::Bulk->label() }}</option>
                                        </flux:select>
                                        <p class="text-xs text-(--text-muted) mt-1">Sale products use Bulk stock method only.</p>
                                    @else
                                        <flux:select wire:model.live="stockMethod" label="Stock Method">
                                            @foreach($stockMethods as $method)
                                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                </div>

                                <flux:select wire:model="allowedStockType" label="Allowed Stock Type">
                                    <option value="1">Rental</option>
                                    <option value="2">Sale</option>
                                    <option value="3">Both</option>
                                </flux:select>
                            </div>
                        </x-signals.form-section>
                    </section>

                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >
                        <x-signals.form-section title="Identification">
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                    <flux:input wire:model="sku" label="SKU" />
                                    <div>
                                        @if($stockMethod == \App\Enums\StockMethod::Serialised->value)
                                            <flux:input wire:model="barcode" label="Barcode" disabled />
                                            <p class="text-xs text-(--text-muted) mt-1">Serialised items get individual barcodes at stock level.</p>
                                        @else
                                            <flux:input wire:model="barcode" label="Barcode" />
                                        @endif
                                    </div>
                                </div>
                                <flux:input wire:model="weight" label="Weight" type="number" step="any" min="0" />
                            </div>
                        </x-signals.form-section>
                    </section>

                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >
                        <div class="space-y-6">

                    {{-- Pricing --}}
                    <x-signals.form-section title="Pricing">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:input wire:model="replacementCharge" label="Replacement Charge" type="number" min="0" step="0.01" />
                                <flux:input wire:model="subRentalPrice" label="Sub-Rental Price" type="number" min="0" step="0.01" />
                            </div>
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                @costs
                                    <flux:input wire:model="purchasePrice" label="Purchase Price" type="number" min="0" step="0.01" />
                                @endcosts
                                <flux:input wire:model="bufferPercent" label="Buffer %" type="number" step="any" min="0" max="100" />
                            </div>
                            <flux:input wire:model.number="postRentUnavailability" label="Post-Rent Unavailability (days)" type="number" min="0" />
                        </div>
                    </x-signals.form-section>

                    {{-- Tax & Revenue --}}
                    <x-signals.form-section title="Tax & Revenue">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="taxClassId" label="Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseTaxClassId" label="Purchase Tax Class">
                                    <option value="">None</option>
                                    @foreach($taxClasses as $taxClass)
                                        <option value="{{ $taxClass->id }}">{{ $taxClass->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="saleTaxCategoryId" label="Sale Tax Category">
                                    <option value="">None</option>
                                    @foreach($taxCategories as $taxCategory)
                                        <option value="{{ $taxCategory->id }}">{{ $taxCategory->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseTaxCategoryId" label="Purchase Tax Category">
                                    <option value="">None</option>
                                    @foreach($taxCategories as $taxCategory)
                                        <option value="{{ $taxCategory->id }}">{{ $taxCategory->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="rentalRevenueGroupId" label="Rental Revenue Group">
                                    <option value="">None</option>
                                    @foreach($revenueGroups as $revGroup)
                                        <option value="{{ $revGroup->id }}">{{ $revGroup->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="saleRevenueGroupId" label="Sale Revenue Group">
                                    <option value="">None</option>
                                    @foreach($revenueGroups as $revGroup)
                                        <option value="{{ $revGroup->id }}">{{ $revGroup->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                <flux:select wire:model="subRentalCostGroupId" label="Sub-Rental Cost Group">
                                    <option value="">None</option>
                                    @foreach($costGroups as $cGroup)
                                        <option value="{{ $cGroup->id }}">{{ $cGroup->name }}</option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="purchaseCostGroupId" label="Purchase Cost Group">
                                    <option value="">None</option>
                                    @foreach($costGroups as $cGroup)
                                        <option value="{{ $cGroup->id }}">{{ $cGroup->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </x-signals.form-section>

                        </div>
                    </section>

                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >
                        <div class="space-y-6">

                            {{-- Other --}}
                            <x-signals.form-section title="Other">
                                <div class="space-y-3">
                                    {{-- Country of Origin autocomplete --}}
                                    <div>
                                        <label class="block text-sm font-medium mb-1">Country of Origin</label>
                                        <div x-data="{ open: false }" class="relative">
                                            <div class="flex items-center gap-1">
                                                @if($this->countryOfOriginId)
                                                    <x-signals.autocomplete-selected :label="$this->selectedCountryName">
                                                        <button type="button" wire:click="$set('countryOfOriginId', null)" class="shrink-0 text-zinc-400 hover:text-zinc-700 dark:hover:text-white">&times;</button>
                                                    </x-signals.autocomplete-selected>
                                                @else
                                                    <flux:input wire:model.live.debounce.300ms="countrySearch" placeholder="Search countries..." x-on:focus="open = true" x-on:input="open = true" />
                                                @endif
                                            </div>
                                            @if(!$this->countryOfOriginId && count($this->countryResults) > 0)
                                                <div x-show="open" x-on:click.outside="open = false" class="s-dropdown" style="position: absolute; top: 100%; left: 0; right: 0; z-index: 50; margin-top: 4px;">
                                                    @foreach($this->countryResults as $country)
                                                        <button type="button" wire:click="selectCountry({{ $country->id }})" x-on:click="open = false" class="s-dropdown-item w-full text-left">{{ $country->name }}</button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <x-dense::field label="Tags" :error="$errors->first('tags')">
                                        <div wire:ignore>
                                            <x-signals.tag-input :value="$tags" :suggestions="$tagSuggestions" placeholder="Add tag..." x-on:tags-changed="$wire.set('tags', $event.detail)" />
                                        </div>
                                    </x-dense::field
                                </div>
                            </x-signals.form-section>

                            {{-- Checkboxes --}}
                            <x-signals.form-section title="Options">
                                <div class="flex items-center gap-6 pt-1 flex-wrap">
                                    <flux:checkbox wire:model="isActive" label="Active" />
                                    <flux:checkbox wire:model="accessoryOnly" label="Accessory Only" />
                                    <flux:checkbox wire:model="discountable" label="Discountable" />
                                </div>
                            </x-signals.form-section>
                        </div>
                    </section>

                    <section
                        x-show="isSelected($id('tab', whichChild($el, $el.parentElement)))"
                        :aria-labelledby="$id('tab', whichChild($el, $el.parentElement))"
                        role="tabpanel"
                        class="space-y-6"
                    >
                        <div class="space-y-6">
                            <x-signals.custom-fields-editor :groupedCustomFields="$groupedCustomFields" />
                        </div>
                    </section>
                </div>

                {{-- Form actions remain available regardless of the selected tab. --}}
                <div class="flex items-center gap-4 pt-2">
                    <flux:button variant="primary" type="submit">{{ $isEditing ? 'Save Changes' : 'Create Product' }}</flux:button>
                    <flux:button variant="ghost" href="{{ $isEditing ? route('products.show', $productId) : route('products.index') }}" wire:navigate.hover>Cancel</flux:button>
                </div>
            </div>
        </form>
    </div>
</section>

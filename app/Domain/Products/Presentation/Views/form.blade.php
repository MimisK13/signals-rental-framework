<section class="w-full">
    @if($isEditing && $product)
        @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Edit'])
        @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => ''])
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
                                            <option value="{{ \App\Domain\Stock\Enums\StockMethod::Bulk->value }}">{{ \App\Domain\Stock\Enums\StockMethod::Bulk->label() }}</option>
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
                                        @if($stockMethod == \App\Domain\Stock\Enums\StockMethod::Serialised->value)
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
                                    </x-dense::field>
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

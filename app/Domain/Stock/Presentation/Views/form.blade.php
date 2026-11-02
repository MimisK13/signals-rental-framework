<section class="w-full">
    @if($isEditing)
        <x-dense::page-header title="Edit Stock Level">
            <x-slot:breadcrumbs>
                <a href="{{ route('stock-levels.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Stock Levels</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <a href="{{ route('stock-levels.show', $stockLevelId) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $selectedProductName ?: 'Stock Level' }}</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Edit</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @else
        <x-dense::page-header title="Create Stock Level">
            <x-slot:breadcrumbs>
                <a href="{{ route('stock-levels.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Stock Levels</a>
                <span class="mx-1 text-[var(--text-muted)]">/</span>
                <span>Create</span>
            </x-slot:breadcrumbs>
        </x-dense::page-header>
    @endif

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if(! $hasStores)
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-6 text-center dark:border-amber-700 dark:bg-amber-950">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">You need to create a store before adding stock levels.</p>
                <a href="{{ route('admin.settings.stores') }}" wire:navigate.hover class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-[var(--link)] hover:underline">
                    Go to Store Settings &rarr;
                </a>
            </div>
        @else
            <form wire:submit="save">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; align-items: start;">
                    {{-- LEFT COLUMN --}}
                    <div class="space-y-6">
                        <x-signals.form-section title="Assignment">
                            <div class="space-y-3">
                                {{-- Product Autocomplete --}}
                                <div>
                                    <flux:label>Product <span class="text-red-500">*</span></flux:label>

                                    @if($productSelected)
                                        <div class="mt-1 flex items-center gap-2">
                                            <div class="flex min-h-10 flex-1 items-center gap-2 rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] px-3 py-2 text-sm text-[var(--text-primary)]">
                                                <span>{{ $selectedProductName }}</span>
                                                @if($stockMethodLabel)
                                                    <x-signals.stock-method-badge :serialised="$isSerialisedStock" :label="$stockMethodLabel . ' Stock'" />
                                                @endif
                                            </div>
                                            @unless($isEditing)
                                                <button type="button" wire:click="clearProduct" class="rounded p-1 text-[var(--text-muted)] hover:bg-[var(--s-subtle)] hover:text-[var(--text-primary)]">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                </button>
                                            @endunless
                                        </div>
                                    @else
                                        <div class="relative mt-1">
                                            <flux:input
                                                wire:model.live.debounce.300ms="productSearch"
                                                placeholder="Search for a product..."
                                                autocomplete="off"
                                            />
                                            @if(count($productResults) > 0)
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] shadow-lg">
                                                    @foreach($productResults as $result)
                                                        <button
                                                            type="button"
                                                            wire:key="product-result-{{ $result['id'] }}"
                                                            wire:click="selectProduct({{ $result['id'] }})"
                                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-[var(--s-subtle)]"
                                                        >
                                                            <span>{{ $result['name'] }}</span>
                                                            @if($result['stock_method'] !== null)
                                                                <x-signals.stock-method-badge :serialised="$result['stock_method'] === 2" />
                                                            @endif
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($productSearch)) >= 1)
                                                <div class="absolute z-50 mt-1 w-full rounded-lg border border-[var(--card-border)] bg-[var(--card-bg)] p-3 text-center text-sm text-[var(--text-muted)] shadow-lg">
                                                    No products found
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @error('productId')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <flux:select wire:model="storeId" label="Store" required>
                                    <option value="">Select a store...</option>
                                    @foreach($stores as $store)
                                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-signals.form-section>

                        {{-- Single / Bulk switcher (serialised create only) --}}
                        @if($isSerialisedStock && ! $isEditing)
                            <div class="flex flex-wrap items-center gap-1">
                                <button type="button" wire:click="setEntryMode('single')"
                                        class="s-chip {{ $entryMode === 'single' ? 'on' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                                    Single
                                </button>
                                <button type="button" wire:click="setEntryMode('bulk')"
                                        class="s-chip {{ $entryMode === 'bulk' ? 'on' : '' }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    Bulk
                                </button>
                            </div>
                        @endif

                        @if($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                            <x-signals.form-section title="Serialised Items">
                                <div class="space-y-3"
                                    x-data
                                    x-on:bulk-row-added.window="$nextTick(() => { const inputs = $root.querySelectorAll('[data-bulk-asset]'); inputs[inputs.length - 1]?.focus(); })"
                                >
                                    @php($bulkGridCols = count($bulkRows) > 1 ? 'grid-cols-[1fr_1fr_2rem]' : 'grid-cols-2')
                                    <div class="grid {{ $bulkGridCols }} gap-4 text-sm font-medium text-[var(--text-primary)] max-sm:hidden">
                                        <span>Asset Number / Barcode</span>
                                        <span>Serial Number</span>
                                        @if(count($bulkRows) > 1)<span></span>@endif
                                    </div>

                                    @foreach($bulkRows as $i => $row)
                                        <div wire:key="bulk-row-{{ $i }}" class="grid {{ $bulkGridCols }} items-center gap-4 max-sm:grid-cols-1">
                                            {{-- Asset Number --}}
                                            <flux:input
                                                wire:model.live.debounce.400ms="bulkRows.{{ $i }}.asset_number"
                                                data-bulk-asset
                                                x-on:keydown.enter.prevent="$wire.addBulkRow()"
                                                aria-label="Asset Number / Barcode"
                                            >
                                                @if($row['asset_status'] === 'valid')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </x-slot>
                                                @elseif($row['asset_status'] === 'duplicate')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </x-slot>
                                                @endif
                                            </flux:input>

                                            {{-- Serial Number --}}
                                            <flux:input
                                                wire:model.live.debounce.400ms="bulkRows.{{ $i }}.serial_number"
                                                x-on:keydown.enter.prevent="$wire.addBulkRow()"
                                                aria-label="Serial Number"
                                            >
                                                @if($row['serial_status'] === 'valid')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </x-slot>
                                                @elseif($row['serial_status'] === 'duplicate')
                                                    <x-slot name="iconTrailing">
                                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </x-slot>
                                                @endif
                                            </flux:input>

                                            {{-- Remove --}}
                                            @if(count($bulkRows) > 1)
                                                <div class="flex justify-center">
                                                    <button type="button" wire:click="removeBulkRow({{ $i }})"
                                                            class="rounded p-1 text-[var(--text-muted)] hover:bg-[var(--s-subtle)] hover:text-[var(--red)]">
                                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <div class="flex items-center gap-3 pt-1">
                                        <button type="button" wire:click="addBulkRow" class="s-btn s-btn-sm">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                                            Add row
                                        </button>
                                        <span class="text-xs text-[var(--text-muted)]">Press Enter to add a row</span>
                                    </div>

                                    @error('bulkRows')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </x-signals.form-section>
                        @else
                            <x-signals.form-section title="Identification">
                                <div class="space-y-3">
                                    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
                                        <flux:input wire:model="assetNumber" label="Asset Number / Barcode" />
                                        <flux:input wire:model="serialNumber" label="Serial Number" />
                                    </div>
                                </div>
                            </x-signals.form-section>
                        @endif

                        <div class="flex items-center gap-4 pt-2">
                            @if($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                                @php($completeRows = $this->completeRowCount())
                                <flux:button variant="primary" type="submit" :disabled="! $this->bulkCanSubmit() || ! $hasStores">
                                    {{ $completeRows > 0 ? 'Create ' . $completeRows . ' Stock ' . \Illuminate\Support\Str::plural('Level', $completeRows) : 'Create Stock Levels' }}
                                </flux:button>
                            @else
                                <flux:button variant="primary" type="submit" :disabled="! $hasStores">{{ $isEditing ? 'Save Changes' : 'Create Stock Level' }}</flux:button>
                            @endif
                            <flux:button
                                variant="ghost"
                                :href="$isEditing
                                    ? route('stock-levels.show', $stockLevelId)
                                    : ($sourceProductId ? route('products.show', $sourceProductId) : route('stock-levels.index'))"
                                wire:navigate.hover
                            >Cancel</flux:button>
                        </div>
                    </div>

                    {{-- RIGHT COLUMN --}}
                    <div class="space-y-6" style="position: sticky; top: 24px;">
                        @unless($isSerialisedStock && $entryMode === 'bulk' && ! $isEditing)
                        <x-signals.form-section title="Stock">
                            <div class="space-y-3">
                                @if($isSerialisedStock)
                                    <div>
                                        <flux:input wire:model.number="quantityHeld" label="Quantity Held" type="number" min="1" max="1" value="1" required disabled />
                                        <p class="mt-1 text-xs text-[var(--text-muted)]">Serialised stock is always quantity 1.</p>
                                    </div>
                                @elseif($isEditing)
                                    <flux:input label="Quantity Held" type="number" :value="$quantityHeld" disabled />
                                    <p class="mt-1 text-xs text-[var(--text-muted)]">Physical quantity is changed through stock transactions.</p>
                                @else
                                    <flux:input wire:model.number="quantityHeld" label="Quantity Held" type="number" min="0" required />
                                @endif
                            </div>
                        </x-signals.form-section>
                        @endunless
                    </div>
                </div>
            </form>
        @endif
    </div>
</section>

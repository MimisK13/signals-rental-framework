<section class="w-full">
    @include('products::partials.product-header', ['product' => $product, 'subpage' => 'Accessories'])
    @include('products::partials.product-tabs', ['product' => $product, 'activeTab' => 'accessories'])

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Toolbar --}}
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--text-secondary)]" style="font-family: var(--font-display); text-transform: uppercase; letter-spacing: 0.04em;">
                Accessories ({{ $product->accessories_count ?? 0 }})
            </h3>
            <button
                x-on:click="$dispatch('open-modal', 'add-accessory')"
                class="s-btn s-btn-sm s-btn-accent"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                Add Accessory
            </button>
        </div>

        {{-- Add Accessory Modal --}}
        <x-signals.modal name="add-accessory" title="Add Accessory" size="md">
            <div class="space-y-3">
                <div>
                    <label class="s-field-label mb-1 block" for="accessory-search">Search Product</label>
                    <input
                        type="text"
                        id="accessory-search"
                        wire:model.live.debounce.300ms="accessorySearch"
                        placeholder="Type to search products..."
                        class="s-input w-full"
                    />
                    @error('selectedAccessoryId') <div class="s-field-error mt-1">{{ $message }}</div> @enderror

                    @if(is_countable($searchResults) && count($searchResults) > 0)
                        <div class="mt-1 rounded border border-[var(--card-border)] bg-[var(--card-bg)] shadow-sm">
                            @foreach($searchResults as $result)
                                <button
                                    wire:key="search-{{ $result->id }}"
                                    wire:click="$set('selectedAccessoryId', {{ $result->id }}); $set('accessorySearch', '{{ addslashes($result->name) }}')"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-[var(--s-subtle)] {{ $selectedAccessoryId === $result->id ? 'bg-[var(--s-subtle)] font-medium' : '' }}"
                                    type="button"
                                >
                                    <span>{{ $result->name }}</span>
                                    @php
                                        $searchBadge = match($result->product_type) {
                                            \App\Domain\Products\Enums\ProductType::Rental => 's-badge-blue',
                                            \App\Domain\Products\Enums\ProductType::Sale => 's-badge-green',
                                            \App\Domain\Products\Enums\ProductType::Service => 's-badge-amber',
                                            \App\Domain\Products\Enums\ProductType::LossAndDamage => 's-badge-red',
                                            default => 's-badge-zinc',
                                        };
                                    @endphp
                                    <span class="s-badge {{ $searchBadge }}" style="font-size: 10px;">{{ $result->product_type->label() }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div style="width: 100px;">
                    <label class="s-field-label mb-1 block" for="accessory-qty">Quantity</label>
                    <input
                        type="number"
                        id="accessory-qty"
                        wire:model="accessoryQuantity"
                        min="1"
                        class="s-input w-full text-right"
                    />
                </div>
            </div>

            <x-slot:footer>
                <button wire:click="resetForm" x-on:click="$dispatch('close-modal', 'add-accessory')" class="s-btn s-btn-sm" type="button">
                    Cancel
                </button>
                <button wire:click="addAccessory" class="s-btn s-btn-sm s-btn-primary" type="button" {{ $selectedAccessoryId ? '' : 'disabled' }}>
                    Add
                </button>
            </x-slot:footer>
        </x-signals.modal>

        @if($product->accessories->isNotEmpty())
            <div class="s-table-wrap">
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th class="text-left">Accessory</th>
                            <th class="text-left">Type</th>
                            <th class="text-right" style="width: 100px;">Quantity</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->accessories as $accessory)
                            <tr wire:key="accessory-{{ $accessory->id }}">
                                <td>
                                    @if($accessory->accessoryProduct)
                                        <a href="{{ route('products.show', $accessory->accessoryProduct) }}" wire:navigate.hover class="s-cell-link">
                                            {{ $accessory->accessoryProduct->name }}
                                        </a>
                                    @else
                                        <span class="text-[var(--text-muted)]">Unknown product</span>
                                    @endif
                                </td>
                                <td>
                                    @if($accessory->accessoryProduct)
                                        @php
                                            $accTypeBadge = match($accessory->accessoryProduct->product_type) {
                                                \App\Domain\Products\Enums\ProductType::Rental => 's-badge-blue',
                                                \App\Domain\Products\Enums\ProductType::Sale => 's-badge-green',
                                                \App\Domain\Products\Enums\ProductType::Service => 's-badge-amber',
                                                \App\Domain\Products\Enums\ProductType::LossAndDamage => 's-badge-red',
                                                default => 's-badge-zinc',
                                            };
                                        @endphp
                                        <span class="s-badge {{ $accTypeBadge }}">{{ $accessory->accessoryProduct->product_type->label() }}</span>
                                    @else
                                        <span class="text-[var(--text-muted)]">—</span>
                                    @endif
                                </td>
                                <td class="text-right s-cell-mono">{{ $accessory->quantity ?? 1 }}</td>
                                <td class="text-right">
                                    <button wire:click="removeAccessory({{ $accessory->id }})" wire:confirm="Remove this accessory?" class="s-btn s-btn-xs s-btn-ghost text-[var(--red)]">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-dense::empty-state title="No Accessories" description="No accessories have been linked to this product. Click 'Add Accessory' above to link one.">
                <x-slot:icon>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-10 opacity-30"><path d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.735-3.684-1.757 1.757a4.5 4.5 0 0 1-6.364 0 4.5 4.5 0 0 1 0-6.364l4.5-4.5a4.5 4.5 0 0 1 6.364 6.364l-1.757 1.757"/></svg>
                </x-slot:icon>
            </x-dense::empty-state>
        @endif
    </div>
</section>

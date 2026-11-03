<section class="w-full">
    <x-dense::page-header title="New Stocktake">
        <x-slot:breadcrumbs>
            <a
                href="{{ route('stocktakes.index') }}"
                wire:navigate.hover
                class="text-[var(--link)] hover:underline"
            >
                Stocktakes
            </a>
        </x-slot:breadcrumbs>
    </x-dense::page-header>

    <div class="px-6 py-4 max-w-2xl max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Bulk physical count">
            <form
                wire:submit="create"
                class="flex flex-col gap-4"
            >
                <flux:select
                    wire:model="storeId"
                    label="Store"
                    required
                >
                    <option value="">Select a Store</option>
                    @foreach ($this->stores() as $store)
                        <option value="{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </flux:select>
                <p class="text-sm text-[var(--text-muted)]">The first D3 slice counts all eligible physical bulk
                    StockLevels in the selected Store. Virtual, serialized, Asset-linked and container-associated levels
                    are excluded.</p>
                <flux:textarea
                    wire:model="notes"
                    label="Notes"
                />
                <div class="flex justify-end gap-2">
                    <a
                        href="{{ route('stocktakes.index') }}"
                        wire:navigate.hover
                        class="s-btn s-btn-sm"
                    >
                        Cancel
                    </a>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="s-btn s-btn-sm s-btn-primary"
                    >
                        Create Stocktake
                    </button>
                </div>
            </form>
        </x-signals.panel>
    </div>
</section>

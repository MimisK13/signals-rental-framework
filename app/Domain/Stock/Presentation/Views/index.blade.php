<section class="w-full">
    <x-dense::page-header title="Stock Levels">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Catalogue</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Status filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                All
            </button>
            <button wire:click="setStatusFilter('available')" class="s-chip {{ $statusFilter === 'available' ? 'on' : '' }}">Available</button>
            <button wire:click="setStatusFilter('allocated')" class="s-chip {{ $statusFilter === 'allocated' ? 'on' : '' }}">Allocated</button>
            <button wire:click="setStatusFilter('quarantined')" class="s-chip {{ $statusFilter === 'quarantined' ? 'on' : '' }}">Quarantined</button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Stock\Models\StockLevel::class"
            :searchable="['item_name', 'asset_number', 'serial_number', 'barcode']"
            :with="['product', 'store']"
            :scopes="$scopes"
            :refresh-events="['stock-level-deleted']"
            default-sort="item_name"
            empty-message="No stock levels found."
            actions-view="stock::partials.row-actions"
            bulk-actions-view="stock::partials.bulk-actions"
            toolbar-view="stock::partials.toolbar"
            entity-type="stock_levels"
            :key="'stock-levels-table-' . $statusFilter"
        />
    </div>
</section>

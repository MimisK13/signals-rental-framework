<section class="w-full">
    <x-dense::page-header title="Products">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">
                Catalogue
            </span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setTypeFilter('')"
                    class="s-chip {{ $typeFilter === '' ? 'on' : '' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                All <span class="s-chip-count">{{ $totalCount }}</span>
            </button>
            @foreach($productTypes as $type)
                <button wire:click="setTypeFilter('{{ $type->value }}')"
                        class="s-chip {{ $typeFilter === $type->value ? 'on' : '' }}">
                    @if($type === \App\Domain\Products\Enums\ProductType::Rental)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                    @elseif($type === \App\Domain\Products\Enums\ProductType::Sale)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    @elseif($type === \App\Domain\Products\Enums\ProductType::Service)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    @endif
                    {{ $type->label() }} <span class="s-chip-count">{{ $typeCounts[$type->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Archive filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setArchiveFilter('active')" class="s-chip {{ $archiveFilter === 'active' ? 'on' : '' }}">Active</button>
            <button wire:click="setArchiveFilter('archived')" class="s-chip {{ $archiveFilter === 'archived' ? 'on' : '' }}">Archived</button>
            <button wire:click="setArchiveFilter('all')" class="s-chip {{ $archiveFilter === 'all' ? 'on' : '' }}">All</button>
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\Products\Models\Product::class"
            :searchable="['name', 'sku', 'barcode']"
            :with="['productGroup']"
            :with-counts="['stockLevels', 'accessories', 'attachments']"
            :scopes="$scopes"
            :refresh-events="['product-archived', 'product-restored']"
            default-sort="product_group_id"
            empty-message="No products found."
            actions-view="products::partials.row-actions"
            bulk-actions-view="products::partials.bulk-actions"
            toolbar-view="products::partials.toolbar"
            entity-type="products"
            :key="'products-table-' . $typeFilter . '-' . $archiveFilter"
        />
    </div>

    <livewire:products.merge-modal />
</section>

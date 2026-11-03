<section class="w-full">
    <x-dense::page-header title="Virtual Stock">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Catalogue</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        @if(count($statuses) > 0)
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">All</button>
                @foreach($statuses as $statusKey => $statusMeta)
                    <button wire:click="setStatusFilter('{{ $statusKey }}')" class="s-chip {{ $statusFilter === $statusKey ? 'on' : '' }}">
                        {{ $statusMeta['label'] ?? $statusKey }}
                    </button>
                @endforeach
            </div>
        @endif

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\VirtualStock\Models\VirtualStockIntake::class"
            :searchable="['number']"
            :with="['product', 'store', 'purchaseOrder']"
            :with-counts="['resolutionItems']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No virtual stock intakes yet."
            toolbar-view="virtual-stock::partials.toolbar"
            entity-type="virtual_stock_intakes"
            :key="'virtual-stock-table-' . $statusFilter"
        />
    </div>
</section>

<section class="w-full">
    <x-dense::page-header title="Purchase Orders">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 'on' : '' }}">
                All <span style="opacity: 0.6;">{{ $totalCount }}</span>
            </button>
            @foreach($statuses as $statusKey => $statusMeta)
                <button wire:click="setStatusFilter('{{ $statusKey }}')" class="s-chip {{ $statusFilter === $statusKey ? 'on' : '' }}">
                    {{ $statusMeta['label'] ?? $statusKey }}
                    <span style="opacity: 0.6;">{{ $statusCounts[$statusKey] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Domain\PurchaseOrders\Models\PurchaseOrder::class"
            :searchable="['number', 'reference']"
            :with="['supplier', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No purchase orders found."
            toolbar-view="purchase-orders::partials.toolbar"
            entity-type="purchase_orders"
            :key="'purchase-orders-table-' . $statusFilter"
        />
    </div>
</section>

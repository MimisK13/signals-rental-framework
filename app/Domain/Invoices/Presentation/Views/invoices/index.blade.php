<section class="w-full">
    <x-dense::page-header title="Invoices">
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
            :model="\App\Domain\Invoices\Models\Invoice::class"
            :searchable="['number', 'reference']"
            :with="['member', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No invoices found."
            toolbar-view="invoices::invoices.partials.toolbar"
            entity-type="invoices"
            :key="'invoices-table-' . $statusFilter"
        />
    </div>
</section>

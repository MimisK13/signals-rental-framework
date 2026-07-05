<?php

use App\Models\VirtualStockIntake;
use App\Views\VirtualStockIntakeColumnRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Virtual Stock')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', VirtualStockIntake::class);
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('virtual_stock.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(VirtualStockIntakeColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $statusOptions = collect(config('virtual_stock.statuses', []))
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        $columns = [
            ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'livewire.virtual-stock.partials.column-number'],
            ['key' => 'product_id', 'label' => 'Product', ...$columnMeta('product_id'), 'view' => 'livewire.virtual-stock.partials.column-product'],
            ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'livewire.virtual-stock.partials.column-store'],
            ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'livewire.virtual-stock.partials.column-status'],
            ['key' => 'available_from', 'label' => 'Window', 'view' => 'livewire.virtual-stock.partials.column-window'],
            ['key' => 'quantity', 'label' => 'Qty', ...$columnMeta('quantity'), 'view' => 'livewire.virtual-stock.partials.column-quantity'],
        ];

        if (Gate::check('costs.view')) {
            array_splice($columns, 5, 0, [[
                'key' => 'unit_cost',
                'label' => 'Total Cost',
                ...$columnMeta('unit_cost'),
                'view' => 'livewire.virtual-stock.partials.column-total-cost',
            ]]);
        }

        return [
            'statuses' => app(\App\Services\VirtualStock\VirtualStockStatusRegistry::class)->statuses(),
            'columns' => $columns,
            'scopes' => $this->statusFilter !== '' ? ['ofStatus' => $this->statusFilter] : [],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Virtual Stock">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Catalogue</span>
        </x-slot:meta>
    </x-signals.page-header>

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
            :model="\App\Models\VirtualStockIntake::class"
            :searchable="['number']"
            :with="['product', 'store', 'purchaseOrder']"
            :with-counts="['resolutionItems']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No virtual stock intakes yet."
            toolbar-view="livewire.virtual-stock.partials.toolbar"
            entity-type="virtual_stock_intakes"
            :key="'virtual-stock-table-' . $statusFilter"
        />
    </div>
</section>

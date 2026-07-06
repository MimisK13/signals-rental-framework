<?php

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use App\Views\PurchaseOrderColumnRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Purchase Orders')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** @var Collection<string, int> */
    public Collection $statusCounts;

    public int $totalCount = 0;

    public function mount(): void
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $this->refreshStatusCounts();
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, config('purchase_orders.statuses', []))) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function refreshStatusCounts(): void
    {
        $this->statusCounts = PurchaseOrder::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->totalCount = (int) $this->statusCounts->sum();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $registry = app(PurchaseOrderColumnRegistry::class);
        $columnMeta = fn (string $key): array => [
            'sortable' => $registry->get($key)?->sortable ?? false,
            'filterable' => $registry->get($key)?->filterable ?? false,
        ];

        $statusOptions = collect(config('purchase_orders.statuses', []))
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        $typeOptions = collect(config('purchase_orders.types', []))
            ->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label'] ?? $key])
            ->all();

        return [
            'statuses' => app(PurchaseOrderStatusRegistry::class)->statuses(),
            'statusCounts' => $this->statusCounts,
            'totalCount' => $this->totalCount,
            'columns' => [
                ['key' => 'number', 'label' => 'Number', ...$columnMeta('number'), 'filter_type' => 'text', 'view' => 'livewire.purchase-orders.partials.column-number'],
                ['key' => 'po_type', 'label' => 'Type', ...$columnMeta('po_type'), 'filter_type' => 'select', 'filter_options' => $typeOptions, 'view' => 'livewire.purchase-orders.partials.column-type'],
                ['key' => 'status', 'label' => 'Status', ...$columnMeta('status'), 'filter_type' => 'select', 'filter_options' => $statusOptions, 'view' => 'livewire.purchase-orders.partials.column-status'],
                ['key' => 'supplier_id', 'label' => 'Supplier', ...$columnMeta('supplier_id'), 'view' => 'livewire.purchase-orders.partials.column-supplier'],
                ['key' => 'store_id', 'label' => 'Store', ...$columnMeta('store_id'), 'view' => 'livewire.purchase-orders.partials.column-store'],
                ['key' => 'total', 'label' => 'Total', ...$columnMeta('total'), 'view' => 'livewire.purchase-orders.partials.column-total'],
                ['key' => 'expected_at', 'label' => 'Expected', ...$columnMeta('expected_at'), 'view' => 'livewire.purchase-orders.partials.column-expected'],
            ],
            'scopes' => $this->statusFilter !== '' ? ['ofStatus' => $this->statusFilter] : [],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Purchase Orders">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
    </x-signals.page-header>

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
            :model="\App\Models\PurchaseOrder::class"
            :searchable="['number', 'reference']"
            :with="['supplier', 'store']"
            :scopes="$scopes"
            default-sort="-created_at"
            empty-message="No purchase orders found."
            toolbar-view="livewire.purchase-orders.partials.toolbar"
            entity-type="purchase_orders"
            :key="'purchase-orders-table-' . $statusFilter"
        />
    </div>
</section>

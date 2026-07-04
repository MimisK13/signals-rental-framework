<?php

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrders\PurchaseOrderStatusRegistry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Purchase Orders')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

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
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function purchaseOrders(): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'store'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $q->where(function ($inner): void {
                    $inner->where('number', 'ilike', '%'.$this->search.'%')
                        ->orWhere('reference', 'ilike', '%'.$this->search.'%')
                        ->orWhereHas('supplier', fn ($m) => $m->where('name', 'ilike', '%'.$this->search.'%'));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'statuses' => app(PurchaseOrderStatusRegistry::class)->statuses(),
            'types' => config('purchase_orders.types', []),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Purchase Orders">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('create', App\Models\PurchaseOrder::class)
                <a href="{{ route('purchase-orders.create') }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                    New Draft
                </a>
            @endcan
        </x-slot:actions>
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

        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search number, reference, or supplier…" icon="magnifying-glass" />
        </div>

        @php $formatter = app(\App\Support\Formatter::class); @endphp

        @if($this->purchaseOrders()->isEmpty())
            <x-signals.empty title="No purchase orders found" description="Create a draft purchase order to order stock or sub-hire equipment." />
        @else
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Supplier</th>
                            <th>Store</th>
                            <th class="text-right">Total</th>
                            <th>Expected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->purchaseOrders() as $purchaseOrder)
                            <tr wire:key="po-{{ $purchaseOrder->id }}">
                                <td>
                                    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
                                        {{ $purchaseOrder->number }}
                                    </a>
                                </td>
                                <td>
                                    <span class="s-badge s-badge-zinc">{{ $types[$purchaseOrder->po_type]['label'] ?? $purchaseOrder->po_type }}</span>
                                </td>
                                <td>
                                    @php $badge = $statuses[$purchaseOrder->status]['badge_class'] ?? 's-badge-zinc'; @endphp
                                    <span class="s-badge {{ $badge }}">{{ $statuses[$purchaseOrder->status]['label'] ?? $purchaseOrder->status }}</span>
                                </td>
                                <td>{{ $purchaseOrder->supplier?->name ?? '—' }}</td>
                                <td>{{ $purchaseOrder->store?->name ?? '—' }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($purchaseOrder->total, $purchaseOrder->currency_code) }}</td>
                                <td>
                                    @if($purchaseOrder->expected_at)
                                        @localdate($purchaseOrder->expected_at)
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-signals.table-wrap>

            <div class="mt-4">
                {{ $this->purchaseOrders()->links() }}
            </div>
        @endif
    </div>
</section>

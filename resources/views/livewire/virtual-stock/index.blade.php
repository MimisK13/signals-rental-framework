<?php

use App\Models\VirtualStockIntake;
use App\Services\Shortages\CostApportionmentRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Virtual Stock')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

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

    public function intakes()
    {
        return VirtualStockIntake::query()
            ->with(['product', 'store', 'purchaseOrder'])
            ->withCount('resolutionItems')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', fn ($q) => $q->where('number', 'ilike', '%'.$this->search.'%'))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function with(): array
    {
        $canViewCosts = Gate::check('costs.view');

        return [
            'statuses' => app(\App\Services\VirtualStock\VirtualStockStatusRegistry::class)->statuses(),
            'canViewCosts' => $canViewCosts,
        ];
    }
}; ?>

<section class="w-full">
    @php
        $formatter = app(\App\Support\Formatter::class);
    @endphp

    <x-signals.page-header title="Virtual Stock">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Finance</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('create', App\Models\VirtualStockIntake::class)
                <a href="{{ route('virtual-stock.create') }}" wire:navigate class="s-btn s-btn-sm s-btn-accent">New Intake</a>
            @endcan
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by number..." />
        </div>

        <x-signals.card>
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Product</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th>Window</th>
                            <th class="text-right">Allocations</th>
                            @if($canViewCosts)
                                <th>Strategy</th>
                                <th class="text-right">Total cost</th>
                            @endif
                            <th class="text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->intakes() as $intake)
                            @php
                                $statusMeta = $statuses[$intake->status] ?? [];
                                $intakeTotalMinor = (int) $intake->unit_cost * (int) $intake->quantity;
                                $strategyKey = $intake->cost_apportionment_strategy ?: (string) settings('shortages.cost_apportionment_strategy', config('shortages.cost_apportionment_strategy', 'primary_job'));
                                $strategyLabel = $canViewCosts && app(CostApportionmentRegistry::class)->has($strategyKey)
                                    ? app(CostApportionmentRegistry::class)->get($strategyKey)->name()
                                    : ($canViewCosts ? $strategyKey : null);
                            @endphp
                            <tr wire:key="vs-intake-{{ $intake->id }}">
                                <td>
                                    <a href="{{ route('virtual-stock.show', $intake) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
                                        {{ $intake->number }}
                                    </a>
                                    @if($intake->isLateReturn())
                                        <span class="s-badge s-badge-amber ml-1">Late</span>
                                    @endif
                                </td>
                                <td>{{ $intake->product?->name ?? '—' }}</td>
                                <td>{{ $intake->store?->name ?? '—' }}</td>
                                <td><span class="s-badge {{ $statusMeta['badge_class'] ?? 's-badge-zinc' }}">{{ $statusMeta['label'] ?? $intake->status }}</span></td>
                                <td class="text-sm">@localdate($intake->available_from) – @localdate($intake->available_until)</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $intake->allocationsCount() }}</td>
                                @if($canViewCosts)
                                    <td class="text-sm">{{ $strategyLabel ?? '—' }}</td>
                                    <td class="text-right" style="font-family: var(--font-mono);">{{ $formatter->money($intakeTotalMinor, $intake->currency_code) }}</td>
                                @endif
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $intake->quantity }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canViewCosts ? 9 : 7 }}" class="text-center text-sm text-[var(--text-muted)] py-8">No virtual stock intakes yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>
            <div class="mt-4">{{ $this->intakes()->links() }}</div>
        </x-signals.card>
    </div>
</section>

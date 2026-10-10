<?php

use App\Models\Stocktake;
use App\Models\Store;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function mount(): void
    {
        Gate::authorize('stock.count');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Stocktake> */
    public function stocktakes()
    {
        $storeIds = auth()->user()?->accessibleStoreIds();

        return Stocktake::query()
            ->with(['store', 'startedBy', 'completedBy'])
            ->withCount('lines')
            ->when($storeIds !== null, fn ($query) => $query->whereIn('store_id', $storeIds))
            ->latest('id')
            ->get();
    }
}; ?>

<section class="w-full">
    <x-dense::page-header title="Stocktakes">
        <x-slot:meta><span class="s-badge s-badge-zinc">Physical observations</span></x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('stocktakes.create') }}" wire:navigate class="s-btn s-btn-sm s-btn-primary">New Stocktake</a>
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Stocktake history">
            @if($this->stocktakes()->isNotEmpty())
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Store</th><th>Status</th><th>Started</th><th>Lines</th><th></th></tr></thead>
                        <tbody>
                            @foreach($this->stocktakes() as $stocktake)
                                <tr wire:key="stocktake-{{ $stocktake->id }}">
                                    <td>{{ $stocktake->store?->name ?? '—' }}</td>
                                    <td><span class="s-badge s-badge-zinc">{{ $stocktake->status->label() }}</span></td>
                                    <td>{{ $stocktake->started_at?->format('d M Y H:i') ?? 'Not started' }}</td>
                                    <td>{{ $stocktake->lines_count ?? $stocktake->lines()->count() }}</td>
                                    <td class="text-right"><a href="{{ route('stocktakes.show', $stocktake) }}" wire:navigate class="s-btn s-btn-xs s-btn-ghost">Open</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-dense::empty-state title="No stocktakes yet" description="Start a bulk physical count for an accessible Store." />
            @endif
        </x-signals.panel>
    </div>
</section>

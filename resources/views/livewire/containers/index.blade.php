<?php

use App\Enums\ContainerStatus;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Containers')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Container::class);
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ContainerStatus::tryFrom($status) === null) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function containers()
    {
        return Container::query()
            ->with(['product', 'store', 'serialisedItem.store'])
            ->withCount('activeItems')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'ilike', $term)
                        ->orWhere('barcode', 'ilike', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function with(): array
    {
        return [
            'statuses' => ContainerStatus::cases(),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Containers">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Stock</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or barcode..." class="max-w-sm" />
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 's-chip-active' : '' }}">All</button>
                @foreach($statuses as $status)
                    <button type="button" wire:click="setStatusFilter('{{ $status->value }}')" wire:key="status-filter-{{ $status->value }}" class="s-chip {{ $statusFilter === $status->value ? 's-chip-active' : '' }}">{{ $status->label() }}</button>
                @endforeach
            </div>
        </div>

        <x-signals.card>
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th class="text-right">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->containers() as $container)
                            <tr wire:key="container-row-{{ $container->uuid }}">
                                <td style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('containers.show', $container) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
                                        {{ $container->name }}
                                    </a>
                                </td>
                                <td><span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span></td>
                                <td>{{ $container->store?->name ?? $container->serialisedItem?->store?->name ?? '—' }}</td>
                                <td>{{ $container->is_temporary ? 'Temporary' : 'Product-backed' }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $container->active_items_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-[var(--muted)] py-8">No containers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>

            <div class="mt-4">
                {{ $this->containers()->links() }}
            </div>
        </x-signals.card>
    </div>
</section>

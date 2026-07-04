<?php

use App\Actions\Containers\CreateContainer;
use App\Data\Containers\CreateContainerData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Store;
use App\Services\Containers\ContainerBarcodeResolver;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Containers')] class extends Component {
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'mode')]
    public string $modeFilter = '';

    #[Url]
    public string $search = '';

    public string $scanBarcode = '';

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

    public function setStoreFilter(string $storeId): void
    {
        $this->storeFilter = $storeId;
    }

    public function setModeFilter(string $mode): void
    {
        if ($mode !== '' && ContainerAvailabilityMode::tryFrom($mode) === null) {
            return;
        }

        $this->modeFilter = $mode;
    }

    public function submitScan(): void
    {
        $resolution = app(ContainerBarcodeResolver::class)->resolve($this->scanBarcode);

        if ($resolution === null) {
            $this->addError('scanBarcode', __('No container or containerable asset matched that barcode.'));

            return;
        }

        if ($resolution['type'] === 'container') {
            $this->redirect(route('containers.show', $resolution['container']), navigate: true);

            return;
        }

        /** @var \App\Models\StockLevel $stockLevel */
        $stockLevel = $resolution['stock_level'];

        if ($resolution['container'] !== null) {
            $this->redirect(route('containers.show', $resolution['container']), navigate: true);

            return;
        }

        if (! Gate::check('create', Container::class)) {
            $this->addError('scanBarcode', __('No active container exists for this housing and you cannot create one.'));

            return;
        }

        $container = (new CreateContainer)(CreateContainerData::from([
            'serialised_item_id' => $stockLevel->id,
        ]));

        $this->scanBarcode = '';
        $this->redirect(route('containers.pack', $container->uuid), navigate: true);
    }

    public function containers()
    {
        return Container::query()
            ->with(['product', 'store', 'serialisedItem.store'])
            ->withCount('activeItems')
            ->when($this->statusFilter === '', fn ($q) => $q->active())
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->storeFilter !== '', fn ($q) => $q->where('store_id', (int) $this->storeFilter))
            ->when($this->modeFilter !== '', function ($q): void {
                $q->whereHas('product', fn ($inner) => $inner->where('container_availability_mode', $this->modeFilter));
            })
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
            'modes' => ContainerAvailabilityMode::cases(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
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
        <x-signals.card class="mb-4">
            <form wire:submit="submitScan" class="flex flex-wrap items-end gap-3">
                <flux:field class="min-w-[16rem] flex-1">
                    <flux:label>Scan barcode</flux:label>
                    <flux:input wire:model="scanBarcode" class="font-mono" placeholder="Container barcode or housing asset number…" autocomplete="off" />
                </flux:field>
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary">Go</flux:button>
            </form>
            @error('scanBarcode')
                <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </x-signals.card>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or barcode..." class="max-w-sm" />
            <flux:select wire:model.live="storeFilter" class="max-w-[10rem]">
                <option value="">All stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" wire:key="store-opt-{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setModeFilter('')" class="s-chip {{ $modeFilter === '' ? 's-chip-active' : '' }}">All modes</button>
                @foreach($modes as $mode)
                    <button type="button" wire:click="setModeFilter('{{ $mode->value }}')" wire:key="mode-filter-{{ $mode->value }}" class="s-chip {{ $modeFilter === $mode->value ? 's-chip-active' : '' }}">{{ $mode->label() }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 's-chip-active' : '' }}">Active</button>
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
                            <th>Mode</th>
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
                                <td>{{ $container->availabilityMode()->label() }}</td>
                                <td>{{ $container->store?->name ?? $container->serialisedItem?->store?->name ?? '—' }}</td>
                                <td>{{ $container->is_temporary ? 'Temporary' : 'Product-backed' }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $container->active_items_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-[var(--muted)] py-8">No containers found.</td>
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

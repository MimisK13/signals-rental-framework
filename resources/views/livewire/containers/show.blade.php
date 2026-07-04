<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Enums\ContainerItemUnpackReason;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Container $container;

    public string $packSerialisedItemId = '';

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);
        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
    }

    public function pack(): void
    {
        Gate::authorize('pack', $this->container);

        (new PackContainerItem)($this->container, PackContainerItemData::from([
            'serialised_item_id' => (int) $this->packSerialisedItemId,
        ]));

        $this->packSerialisedItemId = '';
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
    }

    public function unpack(int $serialisedItemId): void
    {
        Gate::authorize('pack', $this->container);

        (new UnpackContainerItem)($this->container, UnpackContainerItemData::from([
            'serialised_item_id' => $serialisedItemId,
            'reason' => ContainerItemUnpackReason::Manual->value,
        ]));

        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
    }

    public function with(): array
    {
        return [
            'canPack' => Gate::check('pack', $this->container),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Containers</a>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-4">
        <x-signals.card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Barcode</div>
                    <div style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Status</div>
                    <span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Store</div>
                    <div>{{ $container->store?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Type</div>
                    <div>{{ $container->is_temporary ? 'Temporary' : 'Product-backed' }}</div>
                </div>
            </div>
        </x-signals.card>

        @if($canPack && $container->status->acceptsPacking())
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Pack item</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <flux:field class="min-w-[12rem]">
                        <flux:label>Serialised item ID</flux:label>
                        <flux:input wire:model="packSerialisedItemId" type="number" min="1" />
                    </flux:field>
                    <flux:button wire:click="pack" wire:loading.attr="disabled" variant="primary">Pack</flux:button>
                </div>
            </x-signals.card>
        @endif

        <x-signals.card>
            <h2 class="text-sm font-semibold mb-3">Packed items</h2>
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Serialised item</th>
                            <th>Packed at</th>
                            @if($canPack && $container->status->acceptsPacking())
                                <th class="text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($container->activeItems as $item)
                            <tr wire:key="container-item-{{ $item->id }}">
                                <td>{{ $item->product?->name ?? '—' }}</td>
                                <td style="font-family: var(--font-mono);">{{ $item->serialised_item_id }}</td>
                                <td>@localdatetime($item->packed_at)</td>
                                @if($canPack && $container->status->acceptsPacking())
                                    <td class="text-right">
                                        <flux:button size="sm" wire:click="unpack({{ $item->serialised_item_id }})" wire:loading.attr="disabled">Unpack</flux:button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canPack && $container->status->acceptsPacking() ? 4 : 3 }}" class="text-center text-[var(--muted)] py-6">No items packed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>
        </x-signals.card>
    </div>
</section>

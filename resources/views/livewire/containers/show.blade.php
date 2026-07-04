<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\UnpackContainerItem;
use App\Actions\Containers\UnsealContainer;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Container $container;

    public string $packSerialisedItemId = '';

    public string $unsealReason = '';

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);
        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
        $this->unsealReason = ContainerUnsealReason::Manual->value;
    }

    public function pack(): void
    {
        Gate::authorize('pack', $this->container);

        (new PackContainerItem)($this->container, PackContainerItemData::from([
            'serialised_item_id' => (int) $this->packSerialisedItemId,
        ]));

        $this->packSerialisedItemId = '';
        $this->refreshContainer();
    }

    public function unpack(int $serialisedItemId): void
    {
        Gate::authorize('pack', $this->container);

        (new UnpackContainerItem)($this->container, UnpackContainerItemData::from([
            'serialised_item_id' => $serialisedItemId,
            'reason' => ContainerItemUnpackReason::Manual->value,
        ]));

        $this->refreshContainer();
    }

    public function seal(): void
    {
        Gate::authorize('seal', $this->container);

        (new SealContainer)($this->container);

        $this->refreshContainer();
    }

    public function unseal(): void
    {
        Gate::authorize('unseal', $this->container);

        (new UnsealContainer)($this->container, UnsealContainerData::from([
            'reason' => $this->unsealReason,
        ]));

        $this->refreshContainer();
    }

    protected function refreshContainer(): void
    {
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
    }

    public function with(): array
    {
        return [
            'canPack' => Gate::check('pack', $this->container),
            'canSeal' => Gate::check('seal', $this->container),
            'unsealReasons' => ContainerUnsealReason::configValues(),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Containers</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($canSeal && $container->status === ContainerStatus::Open)
                <flux:button wire:click="seal" wire:loading.attr="disabled" variant="primary">Seal</flux:button>
            @endif
            @if($canSeal && $container->status === ContainerStatus::Sealed)
                <div class="flex flex-wrap items-end gap-2">
                    <flux:field>
                        <flux:label>Unseal reason</flux:label>
                        <flux:select wire:model="unsealReason">
                            @foreach($unsealReasons as $reason)
                                <option value="{{ $reason }}">{{ str($reason)->headline() }}</option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <flux:button wire:click="unseal" wire:loading.attr="disabled">Unseal</flux:button>
                </div>
            @endif
        </x-slot:actions>
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
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Scan mode</div>
                    <div>{{ $container->scan_mode->label() }}</div>
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

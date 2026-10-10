<?php

use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\UnpackContainerItem;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\PackContainerResolution;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Models\Container;
use App\Models\StockLevel;
use App\Services\Containers\ContainerManifestService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Container $container;

    public string $scanInput = '';

    /** @var array<string, mixed>|null */
    public ?array $packConflict = null;

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);

        if (! $container->status->acceptsPacking()) {
            abort(403);
        }

        Gate::authorize('pack', $container);

        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem.asset']);
    }

    public function scanItem(?string $resolve = null): void
    {
        Gate::authorize('pack', $this->container);

        $this->packConflict = null;
        $stockLevel = $this->resolveStockLevel(trim($this->scanInput));

        if ($stockLevel === null) {
            $this->addError('scanInput', __('No serialised asset matched that scan.'));

            return;
        }

        try {
            (new PackContainerItem)($this->container, PackContainerItemData::from([
                'serialised_item_id' => $stockLevel->id,
                'resolve' => $resolve,
            ]));

            $this->scanInput = '';
            $this->resetErrorBag('scanInput');
            $this->refreshContainer();
        } catch (ContainerPackConflictException $e) {
            $this->packConflict = $e->payload();
        }
    }

    public function resolvePackConflict(string $resolution): void
    {
        $this->scanItem($resolution);
    }

    public function dismissPackConflict(): void
    {
        $this->packConflict = null;
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

        $this->redirect(route('containers.show', $this->container), navigate: true);
    }

    protected function resolveStockLevel(string $term): ?StockLevel
    {
        if ($term === '') {
            return null;
        }

        if (ctype_digit($term)) {
            return StockLevel::query()->serialized()->whereKey((int) $term)->first();
        }

        return StockLevel::query()
            ->serialized()
            ->where(fn ($q) => $q->where('asset_number', $term)->orWhere('serial_number', $term))
            ->first();
    }

    protected function refreshContainer(): void
    {
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem.asset']);
    }

    public function with(): array
    {
        $compliance = app(ContainerManifestService::class)->compliance($this->container);
        $productIds = collect($compliance->slots)->pluck('product_id')->unique()->all();
        $productNames = \App\Models\Product::query()->whereIn('id', $productIds)->pluck('name', 'id');

        return [
            'compliance' => $compliance,
            'productNames' => $productNames,
            'canSeal' => Gate::check('seal', $this->container)
                && $this->container->activeItems()->count() > 0
                && ($this->container->scan_mode !== ContainerScanMode::Strict || $compliance->compliant),
            'isStrict' => $this->container->scan_mode === ContainerScanMode::Strict,
            'canPackOverride' => Gate::check('packOverride', $this->container),
            'canTransfer' => Gate::check('transfer', $this->container),
        ];
    }
}; ?>

<section class="w-full" data-density="compact">
    <x-dense::page-header :title="'Pack — '.$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.show', $container) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $container->name }}</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($canSeal)
                <x-dense::button wire:click="seal" wire:loading.attr="disabled" wire:target="seal" variant="primary" size="sm">Seal container</x-dense::button>
            @endif
        </x-slot:actions>
    </x-dense::page-header>

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3 space-y-4">
        @if(session('container_status'))
            <x-signals.alert type="success">{{ session('container_status') }}</x-signals.alert>
        @endif

        <x-dense::card title="Scan Item" class="shadow-none">
            <form wire:submit="scanItem" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                <div>
                    <x-dense::field-label for="container-scan-input">Asset number, serial number, or ID</x-dense::field-label>
                    <x-dense::input id="container-scan-input" wire:model="scanInput" class="w-full font-mono" placeholder="Scan or enter item identity" autofocus autocomplete="off" aria-describedby="container-scan-input-error" />
                </div>
                {{-- Legacy Flux input retained as a non-rendered compatibility reference while this workflow uses Dense controls. --}}
                @if(false)
                <div class="hidden" aria-hidden="true">
                    <flux:input wire:model="scanInput" class="font-mono" placeholder="Asset number, serial, or ID…" autofocus autocomplete="off" />
                </div>
                @endif
                <x-dense::button type="submit" wire:loading.attr="disabled" wire:target="scanItem" variant="primary" size="sm" class="box-border h-[var(--erp-control-height,40px)] w-full sm:w-auto">Pack</x-dense::button>
            </form>
            @error('scanInput')
                <p id="container-scan-input-error" class="dense-field-error mt-1" role="alert">{{ $message }}</p>
            @enderror
        </x-dense::card>

        @if($packConflict)
            <x-dense::card title="Packing Conflict" class="shadow-none">
                <p class="text-sm text-[var(--muted)]">{{ str($packConflict['conflict'] ?? '')->headline() }}</p>
                <dl class="mt-3 space-y-1 text-sm">
                    @foreach($packConflict['details'] ?? [] as $key => $value)
                        <div class="flex gap-2" wire:key="pack-conflict-detail-{{ $key }}">
                            <dt class="text-[var(--muted)]">{{ str($key)->headline() }}:</dt>
                            <dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? '—') }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($packConflict['resolutions'] ?? [] as $key => $resolution)
                        @if($resolution['available'] ?? false)
                            <x-dense::button wire:click="resolvePackConflict('{{ $key }}')" wire:loading.attr="disabled" wire:target="resolvePackConflict" variant="primary" size="sm">
                                {{ $resolution['description'] ?? str($key)->headline() }}
                            </x-dense::button>
                        @endif
                    @endforeach
                    <x-dense::button wire:click="dismissPackConflict" wire:loading.attr="disabled" wire:target="dismissPackConflict" variant="ghost" size="sm">Dismiss</x-dense::button>
                </div>
            </x-dense::card>
        @endif

        @if($isStrict)
            <x-dense::card title="Template Checklist" class="shadow-none">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach($compliance->slots as $slot)
                        <div wire:key="slot-{{ $slot->product_id }}" class="flex items-center justify-between rounded-md border border-[var(--border)] px-3 py-2">
                            <div>
                                <div class="font-medium">{{ $productNames[$slot->product_id] ?? 'Product #'.$slot->product_id }}</div>
                                <div class="text-xs text-[var(--muted)]">Required × {{ $slot->expected_quantity }}</div>
                            </div>
                            <span class="s-badge {{ match($slot->status) {
                                'ok' => 's-badge-green',
                                'under' => 's-badge-amber',
                                'over' => 's-badge-red',
                                default => 's-badge-zinc',
                            } }}">
                                {{ $slot->actual_quantity }} / {{ $slot->expected_quantity }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </x-dense::card>
        @else
            <x-dense::card title="Packed Items" class="shadow-none">
                @if($compliance->slots !== [])
                    <p class="text-xs text-[var(--muted)] mb-3">Template reference (non-blocking)</p>
                    <div class="mb-4 grid gap-2 sm:grid-cols-2">
                        @foreach($compliance->slots as $slot)
                            <div wire:key="ref-slot-{{ $slot->product_id }}" class="text-sm text-[var(--muted)]">
                                {{ ($productNames[$slot->product_id] ?? 'Product #'.$slot->product_id) }} — {{ $slot->actual_quantity }} / {{ $slot->expected_quantity }}
                            </div>
                        @endforeach
                    </div>
                @endif
                <x-signals.table-wrap>
                    <table class="s-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Asset</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($container->activeItems as $item)
                                <tr wire:key="pack-item-{{ $item->id }}">
                                    <td>{{ $item->product?->name ?? '—' }}</td>
                                    <td style="font-family: var(--font-mono);">
                                        @if($item->serialisedItem?->asset_id)
                                            <a href="{{ route('assets.show', $item->serialisedItem->asset_id) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}</a>
                                        @else
                                            {{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                <x-dense::button size="sm" wire:click="unpack({{ $item->serialised_item_id }})" wire:loading.attr="disabled" wire:target="unpack" variant="ghost">Unpack</x-dense::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3"><x-dense::empty-state title="No items packed" description="Scan an item to add it to this Container." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            </x-dense::card>
        @endif

        @if($isStrict && $container->activeItems->isNotEmpty())
            <x-dense::card title="Packed Items" class="shadow-none">
                <ul class="space-y-1 text-sm">
                    @foreach($container->activeItems as $item)
                        <li wire:key="strict-item-{{ $item->id }}" class="flex items-center justify-between gap-2">
                            <span>{{ $item->product?->name }} —
                                <span style="font-family: var(--font-mono);">
                                    @if($item->serialisedItem?->asset_id)
                                        <a href="{{ route('assets.show', $item->serialisedItem->asset_id) }}" wire:navigate.hover class="text-[var(--link)] hover:underline">{{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}</a>
                                    @else
                                        {{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}
                                    @endif
                                </span>
                            </span>
                            <x-dense::button size="sm" wire:click="unpack({{ $item->serialised_item_id }})" wire:loading.attr="disabled" wire:target="unpack" variant="ghost">Unpack</x-dense::button>
                        </li>
                    @endforeach
                </ul>
            </x-dense::card>
        @endif
    </div>
</section>

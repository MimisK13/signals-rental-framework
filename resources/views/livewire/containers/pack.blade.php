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

        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
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
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
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

<section class="w-full">
    <x-signals.page-header :title="'Pack — '.$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.show', $container) }}" wire:navigate class="text-[var(--link)] hover:underline">{{ $container->name }}</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($canSeal)
                <flux:button wire:click="seal" wire:loading.attr="disabled" variant="primary">Seal container</flux:button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3 space-y-4">
        <x-signals.card>
            <form wire:submit="scanItem" class="flex flex-wrap items-end gap-3">
                <flux:field class="min-w-[16rem] flex-1">
                    <flux:label>Scan item</flux:label>
                    <flux:input wire:model="scanInput" class="font-mono" placeholder="Asset number, serial, or ID…" autofocus autocomplete="off" />
                </flux:field>
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary">Pack</flux:button>
            </form>
            @error('scanInput')
                <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </x-signals.card>

        @if($packConflict)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-2">Packing conflict</h2>
                <p class="text-sm text-[var(--muted)] mb-3">{{ str($packConflict['conflict'] ?? '')->headline() }}</p>
                <dl class="text-sm space-y-1 mb-4">
                    @foreach($packConflict['details'] ?? [] as $key => $value)
                        <div class="flex gap-2" wire:key="pack-conflict-detail-{{ $key }}">
                            <dt class="text-[var(--muted)]">{{ str($key)->headline() }}:</dt>
                            <dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ($value ?? '—') }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="flex flex-wrap gap-2">
                    @foreach($packConflict['resolutions'] ?? [] as $key => $resolution)
                        @if($resolution['available'] ?? false)
                            <flux:button wire:click="resolvePackConflict('{{ $key }}')" wire:loading.attr="disabled" variant="primary">
                                {{ $resolution['description'] ?? str($key)->headline() }}
                            </flux:button>
                        @endif
                    @endforeach
                    <flux:button wire:click="dismissPackConflict" wire:loading.attr="disabled">Dismiss</flux:button>
                </div>
            </x-signals.card>
        @endif

        @if($isStrict)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Template checklist</h2>
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
            </x-signals.card>
        @else
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Packed items</h2>
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
                                    <td style="font-family: var(--font-mono);">{{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}</td>
                                    <td class="text-right">
                                        <flux:button size="sm" wire:click="unpack({{ $item->serialised_item_id }})" wire:loading.attr="disabled">Unpack</flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-[var(--muted)] py-6">Scan items to pack.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-signals.table-wrap>
            </x-signals.card>
        @endif

        @if($isStrict && $container->activeItems->isNotEmpty())
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Packed items</h2>
                <ul class="space-y-1 text-sm">
                    @foreach($container->activeItems as $item)
                        <li wire:key="strict-item-{{ $item->id }}" class="flex items-center justify-between gap-2">
                            <span>{{ $item->product?->name }} — <span style="font-family: var(--font-mono);">{{ $item->serialisedItem?->asset_number ?? $item->serialised_item_id }}</span></span>
                            <flux:button size="sm" wire:click="unpack({{ $item->serialised_item_id }})" wire:loading.attr="disabled">Unpack</flux:button>
                        </li>
                    @endforeach
                </ul>
            </x-signals.card>
        @endif
    </div>
</section>

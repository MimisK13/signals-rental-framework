<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\ReturnKitContainer;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\StartRepackOnReturn;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Containers\UnpackContainerItem;
use App\Actions\Containers\UnsealContainer;
use App\Actions\Containers\UpdateContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\ReturnKitContainerData;
use App\Data\Containers\StartRepackOnReturnData;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Containers\UnpackContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Data\Containers\UpdateContainerData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\Container;
use App\Models\StockLevel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use HasAuditTimeline;

    public Container $container;

    public string $dispatchOpportunityId = '';

    /** @var array<string, mixed>|null */
    public ?array $dispatchPrompt = null;

    public string $unsealReason = '';

    public string $transferTargetUuid = '';

    public string $transferSerialisedItemId = '';

    public string $editScanMode = '';

    public string $drawerScanInput = '';

    public ?int $pendingUnpackItemId = null;

    public ?int $pendingTransferItemId = null;

    /** @var array<string, mixed>|null */
    public ?array $drawerPackConflict = null;

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);
        $this->container = $container->load(['product', 'store', 'serialisedItem.asset', 'serialisedItem.store', 'activeItems.product', 'activeItems.serialisedItem.asset', 'children']);
        $this->unsealReason = ContainerUnsealReason::Manual->value;
        $this->editScanMode = $container->scan_mode->value;
    }

    public function seal(): void
    {
        Gate::authorize('seal', $this->container);

        (new SealContainer)($this->container);

        $this->refreshContainer();
    }

    public function promptTransferFromContents(int $serialisedItemId): void
    {
        Gate::authorize('transfer', $this->container);

        $this->resetValidation();
        $this->pendingTransferItemId = $serialisedItemId;
        $this->transferSerialisedItemId = (string) $serialisedItemId;
        $this->transferTargetUuid = '';
        $this->dispatch('open-modal', 'transfer-container-item');
    }

    public function confirmTransferFromContents(): void
    {
        Gate::authorize('transfer', $this->container);

        if ($this->pendingTransferItemId === null) {
            return;
        }

        $this->transferSerialisedItemId = (string) $this->pendingTransferItemId;
        $this->transferItem();

        if ($this->getErrorBag()->isEmpty()) {
            $this->pendingTransferItemId = null;
            $this->dispatch('close-modal', 'transfer-container-item');
        }
    }

    public function unseal(): void
    {
        Gate::authorize('unseal', $this->container);

        (new UnsealContainer)($this->container, UnsealContainerData::from([
            'reason' => $this->unsealReason,
        ]));

        $this->refreshContainer();
    }

    public function dissolve(): void
    {
        Gate::authorize('dissolve', $this->container);

        (new DissolveContainer)($this->container);

        $this->refreshContainer();
    }

    public function transferItem(): void
    {
        $this->validate([
            'transferSerialisedItemId' => ['required', 'integer', 'exists:stock_levels,id'],
            'transferTargetUuid' => ['required', 'string', 'uuid', 'exists:containers,uuid'],
        ], [
            'transferSerialisedItemId.required' => __('Enter the serialized item ID.'),
            'transferSerialisedItemId.exists' => __('That serialized item does not exist.'),
            'transferTargetUuid.required' => __('Enter the destination Container UUID.'),
            'transferTargetUuid.uuid' => __('Enter a valid destination Container UUID.'),
            'transferTargetUuid.exists' => __('The destination Container was not found.'),
        ]);

        $target = Container::query()->where('uuid', trim($this->transferTargetUuid))->first();

        if ($target === null) {
            $this->addError('transferTargetUuid', __('The destination Container was not found.'));

            return;
        }

        if ($target->is($this->container)) {
            $this->addError('transferTargetUuid', __('The destination Container must be different from the source.'));

            return;
        }

        Gate::authorize('transfer', $target);

        try {
            (new TransferContainerItem)($target, TransferContainerItemData::from([
                'serialised_item_id' => (int) $this->transferSerialisedItemId,
                'source_container_id' => $this->container->id,
            ]));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError(match ($field) {
                        'serialised_item_id' => 'transferSerialisedItemId',
                        'source_container_id', 'container' => 'transferTargetUuid',
                        default => $field,
                    }, $message);
                }
            }

            return;
        }

        $this->transferTargetUuid = '';
        $this->transferSerialisedItemId = '';
        $this->refreshContainer();
    }

    /**
     * TODO: Keep this legacy execution hook until Container/Kits can be added
     * from the Quotation/Order workflow. The planning entry point must move
     * away from assigning a physical Container from its detail page.
     */
    public function dispatchContainer(?array $amendments = null): void
    {
        Gate::authorize('dispatch', $this->container);

        $this->dispatchPrompt = null;

        $payload = [
            'opportunity_id' => (int) $this->dispatchOpportunityId,
            'auto_add_missing' => true,
        ];

        if ($amendments !== null) {
            $payload['amendments'] = $amendments;
        }

        try {
            (new DispatchContainer)($this->container, DispatchContainerData::from($payload));
            $this->dispatchOpportunityId = '';
            $this->refreshContainer();
        } catch (ContainerDispatchAmendmentRequiredException $e) {
            $this->dispatchPrompt = $e->payload();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $amendments
     */
    public function confirmDispatchAmendments(array $amendments): void
    {
        $this->dispatchContainer($amendments);
    }

    public function dismissDispatchPrompt(): void
    {
        $this->dispatchPrompt = null;
    }

    public function returnKit(): void
    {
        Gate::authorize('return', $this->container);

        (new ReturnKitContainer)($this->container, ReturnKitContainerData::from([]));
        $this->refreshContainer();
    }

    public function verifyCheckin(): void
    {
        Gate::authorize('verifyCheckin', $this->container);

        (new VerifyKitCheckin)($this->container);
        $this->refreshContainer();
    }

    public function saveScanMode(): void
    {
        Gate::authorize('update', $this->container);

        (new UpdateContainer)($this->container, UpdateContainerData::from([
            'scan_mode' => $this->editScanMode,
        ]));

        $this->refreshContainer();
        $this->editScanMode = $this->container->scan_mode->value;
    }

    public function setScanMode(string $scanMode): void
    {
        $this->editScanMode = $scanMode;
        $this->saveScanMode();
    }

    public function packFromDrawer(?string $resolve = null): void
    {
        Gate::authorize('pack', $this->container);

        $this->drawerPackConflict = null;
        $stockLevel = $this->resolveDrawerStockLevel(trim($this->drawerScanInput));

        if ($stockLevel === null) {
            $this->addError('drawerScanInput', __('No serialised asset matched that scan.'));

            return;
        }

        try {
            (new PackContainerItem)($this->container, PackContainerItemData::from([
                'serialised_item_id' => $stockLevel->id,
                'resolve' => $resolve,
            ]));

            $this->drawerScanInput = '';
            $this->resetErrorBag('drawerScanInput');
            $this->refreshContainer();
        } catch (ContainerPackConflictException $exception) {
            $this->drawerPackConflict = $exception->payload();
        }
    }

    public function resolveDrawerPackConflict(string $resolution): void
    {
        $this->packFromDrawer($resolution);
    }

    public function dismissDrawerPackConflict(): void
    {
        $this->drawerPackConflict = null;
    }

    public function unpackItemFromDrawer(int $serialisedItemId): void
    {
        Gate::authorize('pack', $this->container);

        (new UnpackContainerItem)($this->container, UnpackContainerItemData::from([
            'serialised_item_id' => $serialisedItemId,
            'reason' => ContainerItemUnpackReason::Manual->value,
        ]));

        $this->refreshContainer();
    }

    public function promptUnpackFromContents(int $serialisedItemId): void
    {
        Gate::authorize('pack', $this->container);

        $this->pendingUnpackItemId = $serialisedItemId;
        $this->dispatch('open-modal', 'unpack-container-item-confirm');
    }

    public function confirmUnpackFromContents(): void
    {
        Gate::authorize('pack', $this->container);

        if ($this->pendingUnpackItemId === null) {
            return;
        }

        (new UnpackContainerItem)($this->container, UnpackContainerItemData::from([
            'serialised_item_id' => $this->pendingUnpackItemId,
            'reason' => ContainerItemUnpackReason::Manual->value,
        ]));

        $this->pendingUnpackItemId = null;
        $this->dispatch('close-modal', 'unpack-container-item-confirm');
        $this->refreshContainer();
    }

    protected function resolveDrawerStockLevel(string $term): ?StockLevel
    {
        if ($term === '') {
            return null;
        }

        if (ctype_digit($term)) {
            return StockLevel::query()->serialized()->whereKey((int) $term)->first();
        }

        return StockLevel::query()
            ->serialized()
            ->where(fn ($query) => $query->where('asset_number', $term)->orWhere('serial_number', $term))
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contentsTree(): array
    {
        return $this->buildContentsTree($this->container);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildContentsTree(Container $container): array
    {
        $container->loadMissing(['activeItems.product', 'activeItems.serialisedItem.asset', 'children']);

        $nodes = [];

        foreach ($container->activeItems as $item) {
            $nodes[] = [
                'type' => 'item',
                'label' => ($item->product?->name ?? 'Item').' — '.($item->serialisedItem?->asset_number ?? $item->serialised_item_id),
                'asset_id' => $item->serialisedItem?->asset_id,
                'serialised_item_id' => $item->serialised_item_id,
            ];
        }

        foreach ($container->children()->active()->get() as $child) {
            $nodes[] = [
                'type' => 'container',
                'label' => $child->name,
                'uuid' => $child->uuid,
                'children' => $this->buildContentsTree($child),
            ];
        }

        return $nodes;
    }

    protected function timelineColorMap(): array
    {
        return [
            'green' => ['.created', '.sealed', '.dispatched', '.returned', '.repack.started', '.repack.item'],
            'amber' => ['.unsealed'],
            'red' => ['.dissolved'],
            'blue' => ['.item.packed', '.item.unpacked', '.item.transferred', '.nested'],
        ];
    }

    protected function refreshContainer(): void
    {
        $this->container->refresh()->load(['product', 'store', 'serialisedItem.asset', 'serialisedItem.store', 'activeItems.product', 'activeItems.serialisedItem.asset', 'children']);
    }

    /**
     * Keep the selector focused on open Containers at the same Store. The
     * transfer Action remains authoritative for permissions and invariants.
     *
     * @return list<array{value: string, label: string}>
     */
    protected function transferContainerOptions(): array
    {
        $storeId = $this->container->store_id ?? $this->container->serialisedItem?->store_id;

        return Container::query()
            ->with(['store', 'serialisedItem.store'])
            ->where('id', '!=', $this->container->id)
            ->where('status', ContainerStatus::Open->value)
            ->when($storeId !== null, function ($query) use ($storeId): void {
                $query->where(function ($nested) use ($storeId): void {
                    $nested->where('store_id', $storeId)
                        ->orWhereHas('serialisedItem', fn ($stockLevel) => $stockLevel->where('store_id', $storeId));
                });
            })
            ->orderBy('name')
            ->get()
            ->map(static function (Container $target): array {
                $storeName = $target->store?->name ?? $target->serialisedItem?->store?->name;
                $identity = $target->barcode ?: $target->uuid;
                $label = $target->name.' · '.$identity;

                if ($storeName !== null) {
                    $label .= ' · '.$storeName;
                }

                return [
                    'value' => $target->uuid,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    public function with(): array
    {
        $repackOpportunity = $this->container->previous_container_id !== null
            && $this->container->status->acceptsPacking()
            ? $this->container->opportunity_id
            : null;

        return [
            'canPack' => Gate::check('pack', $this->container),
            'canTransfer' => Gate::check('transfer', $this->container),
            'canDissolve' => Gate::check('dissolve', $this->container)
                && ! in_array($this->container->status, [ContainerStatus::Dispatched, ContainerStatus::Returned, ContainerStatus::Dissolved], true),
            'canSeal' => Gate::check('seal', $this->container),
            'canDispatch' => Gate::check('dispatch', $this->container)
                && $this->container->status === ContainerStatus::Sealed,
            'canReturn' => Gate::check('return', $this->container)
                && $this->container->status === ContainerStatus::Dispatched
                && $this->container->availabilityMode()->isBookableKit()
                && ($this->container->product?->container_checkin_mode ?? ContainerCheckinMode::Parent) !== ContainerCheckinMode::Individual,
            'canVerifyCheckin' => Gate::check('verifyCheckin', $this->container)
                && $this->container->checkin_verification_required_at !== null,
            'canEdit' => Gate::check('update', $this->container)
                && $this->container->status->acceptsPacking(),
            'scanModes' => ContainerScanMode::cases(),
            'canApplyDispatchAmendments' => auth()->user()?->can('opportunities.dispatch_add_items') ?? false,
            'checkinModeLabel' => $this->container->product?->container_checkin_mode?->label(),
            'needsCheckinVerification' => $this->container->checkin_verification_required_at !== null,
            'unsealReasons' => collect(ContainerUnsealReason::cases())
                ->filter(fn (ContainerUnsealReason $reason): bool => in_array($reason->value, ContainerUnsealReason::configValues(), true))
                ->values(),
            'timeline' => $this->auditTimelineFor($this->container),
            'repackOpportunityId' => $this->container->opportunity_id,
            'transferContainerOptions' => $this->transferContainerOptions(),
        ];
    }
}; ?>

<section class="w-full" data-density="compact">
    <x-dense::page-header :title="$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.index') }}" wire:navigate.hover class="text-[var(--link)] hover:underline">Containers</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($repackOpportunityId)
                <x-dense::button href="{{ route('containers.repack', ['container' => $container, 'opportunity' => $repackOpportunityId]) }}" wire:navigate.hover variant="ghost" size="sm">Repack session</x-dense::button>
            @endif
        </x-slot:actions>
    </x-dense::page-header>

    @if(session('container_status'))
        <div class="px-6 pt-4 max-md:px-5 max-sm:px-3">
            <x-signals.alert type="success">{{ session('container_status') }}</x-signals.alert>
        </div>
    @endif

    <div class="px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-4">
        <x-dense::card title="Physical Identity" class="shadow-none">
            <x-signals.data-list layout="grid" :dividers="false" :items="array_filter([
                ['label' => 'Barcode', 'value' => $container->barcode ?? '—', 'mono' => true],
                ['label' => 'Store', 'value' => $container->store?->name ?? '—'],
                ['label' => 'Status', 'value' => $container->status->label(), 'badge' => 's-badge-zinc'],
                ['label' => 'Mode', 'value' => $container->availabilityMode()->label(), 'badge' => match($container->availabilityMode()) {
                    ContainerAvailabilityMode::Transport => 's-badge-green',
                    ContainerAvailabilityMode::Kit => 's-badge-blue',
                    ContainerAvailabilityMode::Hybrid => 's-badge-violet',
                }],
                ['label' => 'Scan Mode', 'value' => $container->scan_mode->label(), 'badge' => $container->scan_mode === ContainerScanMode::Free ? 's-badge-green' : 's-badge-violet'],
                $checkinModeLabel ? ['label' => 'Check-in Mode', 'value' => $checkinModeLabel] : null,
            ])" />

            @if($needsCheckinVerification)
                <div class="mt-3">
                    <span class="s-badge s-badge-amber">Verify check-in</span>
                </div>
            @endif

        </x-dense::card>

        {{-- Legacy Details markup retained as a reference during the Physical Identity card migration. --}}
        {{--
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Barcode</div>
                    <div style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Status</div>
                    <span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span>
                    @if($needsCheckinVerification)
                        <span class="s-badge s-badge-amber ml-2">Verify check-in</span>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Mode</div>
                    <span class="s-badge {{ match($container->availabilityMode()) {
                        ContainerAvailabilityMode::Transport => 's-badge-green',
                        ContainerAvailabilityMode::Kit => 's-badge-blue',
                        ContainerAvailabilityMode::Hybrid => 's-badge-violet',
                    } }}">
                        {{ $container->availabilityMode()->label() }}
                    </span>
                </div>
                @if($checkinModeLabel)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Check-in mode</div>
                        <div>{{ $checkinModeLabel }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Scan mode</div>
                    <span class="s-badge {{ $container->scan_mode === ContainerScanMode::Free ? 's-badge-green' : 's-badge-violet' }}">
                        {{ $container->scan_mode->label() }}
                    </span>
                    @if($canEdit)
                        <form wire:submit="saveScanMode" class="mt-1 flex flex-wrap items-end gap-2">
                            <x-dense::select id="container-scan-mode" wire:model="editScanMode" class="min-w-[8rem]">
                                @foreach($scanModes as $mode)
                                    <option value="{{ $mode->value }}" wire:key="scan-mode-{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </x-dense::select>
                            <x-dense::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="saveScanMode">{{ __('Save') }}</x-dense::button>
                        </form>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Store</div>
                    <div>{{ $container->store?->name ?? '—' }}</div>
                </div>
            </div>
        --}}

        @if($dispatchPrompt)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-2">Dispatch requires opportunity amendments</h2>
                <p class="text-sm text-[var(--muted)] mb-3">{{ $dispatchPrompt['message'] ?? '' }}</p>
                <div class="space-y-2 mb-4">
                    @foreach($dispatchPrompt['amendments'] ?? [] as $amendment)
                        <div class="text-sm border border-[var(--border)] rounded-md p-3" wire:key="dispatch-amendment-{{ $amendment['product_id'] }}">
                            <div class="font-medium">{{ $amendment['product_name'] ?? 'Product #'.$amendment['product_id'] }}</div>
                            <div class="text-[var(--muted)]">
                                {{ str($amendment['reason'] ?? '')->headline() }} —
                                suggest {{ $amendment['required_action'] ?? 'add' }}
                                × {{ $amendment['suggested_quantity'] ?? '1' }}
                            </div>
                        </div>
                    @endforeach
                    @foreach($dispatchPrompt['validation'] ?? [] as $row)
                        <div class="text-xs text-[var(--muted)] font-mono" wire:key="dispatch-validation-{{ $row['product_id'] ?? $loop->index }}">
                            {{ $row['product_name'] ?? '' }}: {{ $row['verdict'] ?? '' }} (container {{ $row['container_quantity'] ?? '?' }} / opportunity {{ $row['opportunity_quantity'] ?? '?' }})
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($canApplyDispatchAmendments)
                        <flux:button
                            wire:click="confirmDispatchAmendments({{ json_encode(array_map(
                                static fn (array $row): array => [
                                    'product_id' => $row['product_id'],
                                    'action' => $row['required_action'] ?? 'add',
                                    'quantity' => $row['suggested_quantity'] ?? '1',
                                    'price' => $row['suggested_price'] ?? null,
                                ],
                                $dispatchPrompt['amendments'] ?? [],
                            )) }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmDispatchAmendments"
                            variant="primary"
                        >
                            Apply amendments and dispatch
                        </flux:button>
                    @endif
                    <x-dense::button wire:click="dismissDispatchPrompt" wire:loading.attr="disabled" wire:target="dismissDispatchPrompt" size="sm">Dismiss</x-dense::button>
                </div>
            </x-dense::card>
        @endif

        @if($canReturn)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-3">Return kit</h2>
                        <x-dense::button wire:click="returnKit" wire:loading.attr="disabled" wire:target="returnKit" variant="primary" size="sm">Return kit</x-dense::button>
            </x-dense::card>
        @endif

        @if($canVerifyCheckin)
            <x-dense::card class="shadow-none">
                <h2 class="text-sm font-semibold mb-3">Check-in verification</h2>
                <x-dense::button wire:click="verifyCheckin" wire:loading.attr="disabled" wire:target="verifyCheckin" variant="primary" size="sm">Mark verified</x-dense::button>
            </x-dense::card>
        @endif

        @if($canPack && $container->status->acceptsPacking())
            <div class="flex flex-wrap items-center gap-2">
                <x-dense::button href="{{ route('containers.pack', $container) }}" wire:navigate.hover variant="primary" size="sm">Pack items</x-dense::button>
                <button type="button" x-on:click="$dispatch('open-modal', 'container-pack-modal')" class="button button-ghost button-sm">Quick pack</button>
            </div>
        @endif

        <x-dense::table-card title="Contents" class="shadow-none">
            @if($this->contentsTree() === [])
                <x-dense::empty-state title="No items packed" description="Pack items to build this Container's contents." />
            @else
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Item</th>
                            @if(($canPack || $canTransfer) && $container->status->acceptsPacking())
                                <th class="text-right" style="text-align: right;">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('livewire.containers.partials.contents-tree', ['nodes' => $this->contentsTree(), 'depth' => 0, 'canUnpack' => $canPack && $container->status->acceptsPacking(), 'canTransfer' => $canTransfer && $container->status->acceptsPacking()])
                    </tbody>
                </table>
            @endif
        </x-dense::table-card>

        {{-- TODO: This is a legacy execution hook. The future planning entry
             point is Quotation/Order, where Containers and Kits are selected
             before warehouse dispatch. --}}
        @if($canDispatch)
            <x-dense::card class="shadow-none">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-semibold">Dispatch to opportunity</h2>
                    <span class="s-badge s-badge-amber">TODO</span>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[12rem]">
                        <x-dense::field-label for="container-opportunity-id">Opportunity ID</x-dense::field-label>
                        <x-dense::input id="container-opportunity-id" wire:model="dispatchOpportunityId" type="number" min="1" />
                    </div>
                    <x-dense::button wire:click="dispatchContainer" wire:loading.attr="disabled" wire:target="dispatchContainer" variant="primary" size="sm">Dispatch</x-dense::button>
                </div>
            </x-dense::card>
        @endif

            </div>

            <aside class="min-w-0 space-y-4">
        @if($canDissolve || $canEdit || ($canSeal && $container->status === ContainerStatus::Sealed))
            <div class="space-y-3">
                @if($canSeal && $container->status === ContainerStatus::Open)
                    <x-dense::button wire:click="seal" wire:loading.attr="disabled" wire:target="seal" variant="primary" size="lg" class="w-full">Seal</x-dense::button>
                @endif

                @if($canSeal && $container->status === ContainerStatus::Sealed)
                    <div>
                        <x-dense::field-label for="container-unseal-reason">Unseal reason</x-dense::field-label>
                        <div class="relative">
                            <x-dense::select id="container-unseal-reason" wire:model="unsealReason" class="w-full appearance-none pr-9">
                                @foreach($unsealReasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </x-dense::select>
                            <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]">
                                <path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <x-dense::button wire:click="unseal" wire:loading.attr="disabled" wire:target="unseal" wire:confirm="Unseal this container and allow its contents to be changed?" size="lg" class="w-full">Unseal</x-dense::button>
                @endif

                @if($canDissolve)
                    <x-dense::button wire:click="dissolve" wire:loading.attr="disabled" wire:target="dissolve" wire:confirm="Dissolve this container and release all contents?" variant="danger" size="lg" class="w-full">Dissolve</x-dense::button>
                @endif

                @if($canEdit)
                    <div class="space-y-2">
                        <x-dense::field-label>Scan mode</x-dense::field-label>
                        <div class="inline-flex w-full gap-1" role="tablist" aria-label="Scan mode">
                            @foreach($scanModes as $mode)
                                <x-dense::button
                                    type="button"
                                    wire:click="setScanMode('{{ $mode->value }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="setScanMode"
                                    variant="{{ $container->scan_mode === $mode ? 'primary' : 'ghost' }}"
                                    size="sm"
                                    role="tab"
                                    aria-selected="{{ $container->scan_mode === $mode ? 'true' : 'false' }}"
                                    class="flex-1"
                                >
                                    {{ $mode->label() }}
                                </x-dense::button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-dense::card title="Lifecycle" class="shadow-none">
            @if($timeline->isEmpty())
                <p class="text-sm text-[var(--muted)]">No audit events recorded yet.</p>
            @else
                <x-signals.timeline>
                    @foreach($timeline as $entry)
                        <x-signals.timeline-item
                            wire:key="timeline-{{ $loop->index }}"
                            :color="$entry['color']"
                            :title="$entry['title']"
                            :meta="$entry['meta']"
                        >
                            @if($entry['body'])
                                <p class="text-sm text-[var(--muted)]">{{ $entry['body'] }}</p>
                            @endif
                        </x-signals.timeline-item>
                    @endforeach
                </x-signals.timeline>
            @endif
        </x-dense::card>
            </aside>
        </div>
    </div>

    <x-signals.modal id="container-pack-modal" name="container-pack-modal" title="Quick Pack" size="lg" :teleport="false">
        <div class="space-y-4" data-density="compact">
            <form wire:submit="packFromDrawer" class="space-y-2">
                <div>
                    <x-dense::field-label for="container-drawer-scan-input">Asset number, serial number, or ID</x-dense::field-label>
                    <x-dense::input
                        id="container-drawer-scan-input"
                        wire:model="drawerScanInput"
                        class="w-full font-mono"
                        placeholder="Scan or enter item identity"
                        autocomplete="off"
                        aria-describedby="container-drawer-scan-input-error"
                    />
                </div>
                @error('drawerScanInput')
                    <p id="container-drawer-scan-input-error" class="dense-field-error" role="alert">{{ $message }}</p>
                @enderror
                <x-dense::button type="submit" wire:loading.attr="disabled" wire:target="packFromDrawer" variant="primary" size="lg" class="w-full">Pack item</x-dense::button>
            </form>

            @if($drawerPackConflict)
                <div class="border-t border-[var(--border)] pt-4">
                    <div class="text-sm font-semibold">Packing conflict</div>
                    <p class="mt-1 text-sm text-[var(--muted)]">{{ str($drawerPackConflict['conflict'] ?? '')->headline() }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($drawerPackConflict['resolutions'] ?? [] as $key => $resolution)
                            @if($resolution['available'] ?? false)
                                <x-dense::button wire:click="resolveDrawerPackConflict('{{ $key }}')" wire:loading.attr="disabled" wire:target="resolveDrawerPackConflict" variant="primary" size="sm">
                                    {{ $resolution['description'] ?? str($key)->headline() }}
                                </x-dense::button>
                            @endif
                        @endforeach
                        <x-dense::button wire:click="dismissDrawerPackConflict" wire:loading.attr="disabled" wire:target="dismissDrawerPackConflict" variant="ghost" size="sm">Dismiss</x-dense::button>
                    </div>
                </div>
            @endif

        </div>
    </x-signals.modal>

    <x-signals.modal name="transfer-container-item" title="Transfer item" size="md" :teleport="false">
        <div class="space-y-3">
            <p class="text-sm text-[var(--muted)]">
                Move the selected serialized item from this Container to another Container.
            </p>

            <div>
                <x-dense::field-label for="container-transfer-target">Destination Container</x-dense::field-label>
                <x-signals.combobox
                    name="transferTargetContainer"
                    input-id="container-transfer-target"
                    :value="$transferTargetUuid ?: null"
                    :options="$transferContainerOptions"
                    placeholder="Search Containers by name or barcode"
                    class="w-full"
                    x-on:combobox-selected.window="if ($event.detail.name === 'transferTargetContainer') $wire.set('transferTargetUuid', $event.detail.value ?? '')"
                />
                @error('transferTargetUuid')
                    <p id="container-transfer-target-error" class="dense-field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'transfer-container-item')">
                Cancel
            </button>
            <button type="button" class="s-btn s-btn-primary" wire:click="confirmTransferFromContents" wire:loading.attr="disabled" wire:target="confirmTransferFromContents">
                Transfer
            </button>
        </x-slot:footer>
    </x-signals.modal>

    <x-signals.modal name="unpack-container-item-confirm" title="Unpack item" size="sm" :teleport="false">
    <p class="text-sm text-[var(--muted)]">
        This will remove the serialized item from the Container. The inventory identity and quantity will not be changed.
    </p>

    <x-slot:footer>
        <button type="button" class="s-btn s-btn-ghost" x-on:click="$dispatch('close-modal', 'unpack-container-item-confirm')">
            Cancel
        </button>
        <button type="button" class="s-btn s-btn-danger" wire:click="confirmUnpackFromContents" wire:loading.attr="disabled" wire:target="confirmUnpackFromContents">
            Unpack
        </button>
    </x-slot:footer>
    </x-signals.modal>
</section>

<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\DissolveContainer;
use App\Actions\Containers\ReturnKitContainer;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\StartRepackOnReturn;
use App\Actions\Containers\TransferContainerItem;
use App\Actions\Containers\UnsealContainer;
use App\Actions\Containers\UpdateContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\ReturnKitContainerData;
use App\Data\Containers\StartRepackOnReturnData;
use App\Data\Containers\TransferContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Data\Containers\UpdateContainerData;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Livewire\Concerns\HasAuditTimeline;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
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

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);
        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem', 'children']);
        $this->unsealReason = ContainerUnsealReason::Manual->value;
        $this->editScanMode = $container->scan_mode->value;
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

    public function dissolve(): void
    {
        Gate::authorize('dissolve', $this->container);

        (new DissolveContainer)($this->container);

        $this->refreshContainer();
    }

    public function transferItem(): void
    {
        $target = Container::query()->where('uuid', trim($this->transferTargetUuid))->firstOrFail();

        Gate::authorize('transfer', $target);

        (new TransferContainerItem)($target, TransferContainerItemData::from([
            'serialised_item_id' => (int) $this->transferSerialisedItemId,
            'source_container_id' => $this->container->id,
        ]));

        $this->transferTargetUuid = '';
        $this->transferSerialisedItemId = '';
        $this->refreshContainer();
    }

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
        $container->loadMissing(['activeItems.product', 'activeItems.serialisedItem', 'children']);

        $nodes = [];

        foreach ($container->activeItems as $item) {
            $nodes[] = [
                'type' => 'item',
                'label' => ($item->product?->name ?? 'Item').' — '.($item->serialisedItem?->asset_number ?? $item->serialised_item_id),
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
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem', 'children']);
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
            'unsealReasons' => ContainerUnsealReason::configValues(),
            'timeline' => $this->auditTimelineFor($this->container),
            'repackOpportunityId' => $this->container->opportunity_id,
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header :title="$container->name">
        <x-slot:meta>
            <a href="{{ route('containers.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Containers</a>
        </x-slot:meta>
        <x-slot:actions>
            @if($canPack && $container->status->acceptsPacking())
                <a href="{{ route('containers.pack', $container) }}" wire:navigate class="s-btn s-btn-primary">Pack items</a>
            @endif
            @if($repackOpportunityId)
                <a href="{{ route('containers.repack', ['container' => $container, 'opportunity' => $repackOpportunityId]) }}" wire:navigate class="s-btn s-btn-ghost">Repack session</a>
            @endif
            @if($canSeal && $container->status === ContainerStatus::Open)
                <flux:button wire:click="seal" wire:loading.attr="disabled">Seal</flux:button>
            @endif
            @if($canDissolve)
                <flux:button wire:click="dissolve" wire:loading.attr="disabled" wire:confirm="Dissolve this container and release all contents?">Dissolve</flux:button>
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
                    @if($needsCheckinVerification)
                        <span class="s-badge s-badge-amber ml-2">Verify check-in</span>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Mode</div>
                    <div>{{ $container->availabilityMode()->label() }}</div>
                </div>
                @if($checkinModeLabel)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Check-in mode</div>
                        <div>{{ $checkinModeLabel }}</div>
                    </div>
                @endif
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Scan mode</div>
                    @if($canEdit)
                        <form wire:submit="saveScanMode" class="mt-1 flex flex-wrap items-end gap-2">
                            <flux:select wire:model="editScanMode" class="min-w-[8rem]">
                                @foreach($scanModes as $mode)
                                    <option value="{{ $mode->value }}" wire:key="scan-mode-{{ $mode->value }}">{{ $mode->label() }}</option>
                                @endforeach
                            </flux:select>
                            <flux:button type="submit" size="sm" wire:loading.attr="disabled">{{ __('Save') }}</flux:button>
                        </form>
                    @else
                        <div>{{ $container->scan_mode->label() }}</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Store</div>
                    <div>{{ $container->store?->name ?? '—' }}</div>
                </div>
            </div>
        </x-signals.card>

        @if($dispatchPrompt)
            <x-signals.card>
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
                            variant="primary"
                        >
                            Apply amendments and dispatch
                        </flux:button>
                    @endif
                    <flux:button wire:click="dismissDispatchPrompt" wire:loading.attr="disabled">Dismiss</flux:button>
                </div>
            </x-signals.card>
        @endif

        @if($canReturn)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Return kit</h2>
                <flux:button wire:click="returnKit" wire:loading.attr="disabled" variant="primary">Return kit</flux:button>
            </x-signals.card>
        @endif

        @if($canVerifyCheckin)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Check-in verification</h2>
                <flux:button wire:click="verifyCheckin" wire:loading.attr="disabled" variant="primary">Mark verified</flux:button>
            </x-signals.card>
        @endif

        @if($canDispatch)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Dispatch to opportunity</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <flux:field class="min-w-[12rem]">
                        <flux:label>Opportunity ID</flux:label>
                        <flux:input wire:model="dispatchOpportunityId" type="number" min="1" />
                    </flux:field>
                    <flux:button wire:click="dispatchContainer" wire:loading.attr="disabled" variant="primary">Dispatch</flux:button>
                </div>
            </x-signals.card>
        @endif

        @if($canTransfer && $container->status->acceptsPacking())
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Transfer item out</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <flux:field class="min-w-[10rem]">
                        <flux:label>Item ID</flux:label>
                        <flux:input wire:model="transferSerialisedItemId" type="number" min="1" />
                    </flux:field>
                    <flux:field class="min-w-[12rem]">
                        <flux:label>Target container UUID</flux:label>
                        <flux:input wire:model="transferTargetUuid" class="font-mono" />
                    </flux:field>
                    <flux:button wire:click="transferItem" wire:loading.attr="disabled">Transfer</flux:button>
                </div>
            </x-signals.card>
        @endif

        <x-signals.card>
            <h2 class="text-sm font-semibold mb-3">Contents</h2>
            @if($this->contentsTree() === [])
                <p class="text-sm text-[var(--muted)]">No items packed.</p>
            @else
                @include('livewire.containers.partials.contents-tree', ['nodes' => $this->contentsTree(), 'depth' => 0])
            @endif
        </x-signals.card>

        <x-signals.card>
            <h2 class="text-sm font-semibold mb-3">Lifecycle</h2>
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
        </x-signals.card>
    </div>
</section>

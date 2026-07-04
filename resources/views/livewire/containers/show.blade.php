<?php

use App\Actions\Containers\DispatchContainer;
use App\Actions\Containers\PackContainerItem;
use App\Actions\Containers\ReturnKitContainer;
use App\Actions\Containers\SealContainer;
use App\Actions\Containers\UnpackContainerItem;
use App\Actions\Containers\UnsealContainer;
use App\Actions\Containers\VerifyKitCheckin;
use App\Data\Containers\DispatchContainerData;
use App\Data\Containers\PackContainerItemData;
use App\Data\Containers\ReturnKitContainerData;
use App\Data\Containers\UnpackContainerItemData;
use App\Data\Containers\UnsealContainerData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerCheckinMode;
use App\Enums\ContainerItemUnpackReason;
use App\Enums\ContainerStatus;
use App\Enums\ContainerUnsealReason;
use App\Enums\PackContainerResolution;
use App\Exceptions\Containers\ContainerDispatchAmendmentRequiredException;
use App\Exceptions\Containers\ContainerPackConflictException;
use App\Models\Container;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Container $container;

    public string $packSerialisedItemId = '';

    public string $dispatchOpportunityId = '';

    /** @var array<string, mixed>|null */
    public ?array $dispatchPrompt = null;

    public string $unsealReason = '';

    /** @var array<string, mixed>|null */
    public ?array $packConflict = null;

    public function mount(Container $container): void
    {
        Gate::authorize('view', $container);
        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
        $this->unsealReason = ContainerUnsealReason::Manual->value;
    }

    public function pack(?string $resolve = null): void
    {
        Gate::authorize('pack', $this->container);

        $this->packConflict = null;

        try {
            (new PackContainerItem)($this->container, PackContainerItemData::from([
                'serialised_item_id' => (int) $this->packSerialisedItemId,
                'resolve' => $resolve,
            ]));

            $this->packSerialisedItemId = '';
            $this->refreshContainer();
        } catch (ContainerPackConflictException $e) {
            $this->packConflict = $e->payload();
        }
    }

    public function resolvePackConflict(string $resolution): void
    {
        $this->pack($resolution);
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

    protected function refreshContainer(): void
    {
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
    }

    public function with(): array
    {
        return [
            'canPack' => Gate::check('pack', $this->container),
            'canPackOverride' => Gate::check('packOverride', $this->container),
            'canTransfer' => Gate::check('transfer', $this->container),
            'canNest' => Gate::check('nest', $this->container),
            'canSeal' => Gate::check('seal', $this->container),
            'canDispatch' => Gate::check('dispatch', $this->container)
                && $this->container->status === ContainerStatus::Sealed,
            'canReturn' => Gate::check('return', $this->container)
                && $this->container->status === ContainerStatus::Dispatched
                && $this->container->availabilityMode()->isBookableKit()
                && ($this->container->product?->container_checkin_mode ?? ContainerCheckinMode::Parent) !== ContainerCheckinMode::Individual,
            'canVerifyCheckin' => Gate::check('verifyCheckin', $this->container)
                && $this->container->checkin_verification_required_at !== null,
            'checkinModeLabel' => $this->container->product?->container_checkin_mode?->label(),
            'needsCheckinVerification' => $this->container->checkin_verification_required_at !== null,
            'unsealReasons' => ContainerUnsealReason::configValues(),
            'packResolutions' => array_map(
                static fn (PackContainerResolution $case): string => $case->value,
                PackContainerResolution::cases(),
            ),
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
                    @if($needsCheckinVerification)
                        <span class="s-badge s-badge-amber ml-2">Verify check-in</span>
                    @endif
                </div>
                @if($checkinModeLabel)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-[var(--muted)]">Check-in mode</div>
                        <div>{{ $checkinModeLabel }}</div>
                    </div>
                @endif
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

        @if($packConflict)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-2">Packing conflict</h2>
                <p class="text-sm text-[var(--muted)] mb-3">{{ str($packConflict['conflict'] ?? '')->headline() }}</p>
                <dl class="text-sm space-y-1 mb-4">
                    @foreach($packConflict['details'] ?? [] as $key => $value)
                        <div class="flex gap-2">
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

        @if($dispatchPrompt)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-2">Dispatch requires opportunity amendments</h2>
                <p class="text-sm text-[var(--muted)] mb-3">{{ $dispatchPrompt['message'] ?? '' }}</p>
                <div class="space-y-2 mb-4">
                    @foreach($dispatchPrompt['amendments'] ?? [] as $amendment)
                        <div class="text-sm border border-[var(--border)] rounded-md p-3" wire:key="dispatch-amendment-{{ $amendment['product_id'] }}">
                            <div class="font-medium">{{ $amendment['product_name'] ?? 'Product' }}</div>
                            <div class="text-[var(--muted)]">
                                {{ str($amendment['reason'] ?? '')->headline() }} —
                                suggest {{ $amendment['required_action'] ?? 'add' }}
                                × {{ $amendment['suggested_quantity'] ?? '1' }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
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
                    <flux:button wire:click="dismissDispatchPrompt" wire:loading.attr="disabled">Dismiss</flux:button>
                </div>
            </x-signals.card>
        @endif

        @if($canReturn)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Return kit</h2>
                <p class="text-sm text-[var(--muted)] mb-3">Scan the container barcode to return all contents from the opportunity.</p>
                <flux:button wire:click="returnKit" wire:loading.attr="disabled" variant="primary">Return kit</flux:button>
            </x-signals.card>
        @endif

        @if($canVerifyCheckin)
            <x-signals.card>
                <h2 class="text-sm font-semibold mb-3">Check-in verification</h2>
                <p class="text-sm text-[var(--muted)] mb-3">Condition assessment is pending for this kit.</p>
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

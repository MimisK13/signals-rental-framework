<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Livewire;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\DispatchContainer;
use App\Domain\Warehouse\Containers\Actions\DissolveContainer;
use App\Domain\Warehouse\Containers\Actions\PackContainerItem;
use App\Domain\Warehouse\Containers\Actions\ReturnKitContainer;
use App\Domain\Warehouse\Containers\Actions\SealContainer;
use App\Domain\Warehouse\Containers\Actions\TransferContainerItem;
use App\Domain\Warehouse\Containers\Actions\UnpackContainerItem;
use App\Domain\Warehouse\Containers\Actions\UnsealContainer;
use App\Domain\Warehouse\Containers\Actions\UpdateContainer;
use App\Domain\Warehouse\Containers\Actions\VerifyKitCheckin;
use App\Domain\Warehouse\Containers\Data\DispatchContainerData;
use App\Domain\Warehouse\Containers\Data\PackContainerItemData;
use App\Domain\Warehouse\Containers\Data\ReturnKitContainerData;
use App\Domain\Warehouse\Containers\Data\TransferContainerItemData;
use App\Domain\Warehouse\Containers\Data\UnpackContainerItemData;
use App\Domain\Warehouse\Containers\Data\UnsealContainerData;
use App\Domain\Warehouse\Containers\Data\UpdateContainerData;
use App\Domain\Warehouse\Containers\Enums\ContainerCheckinMode;
use App\Domain\Warehouse\Containers\Enums\ContainerItemUnpackReason;
use App\Domain\Warehouse\Containers\Enums\ContainerScanMode;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Enums\ContainerUnsealReason;
use App\Domain\Warehouse\Containers\Exceptions\ContainerDispatchAmendmentRequiredException;
use App\Domain\Warehouse\Containers\Exceptions\ContainerPackConflictException;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Livewire\Concerns\HasAuditTimeline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
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
    /** @param list<array<string, mixed>>|null $amendments */
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
                'label' => $item->product->name.' — '.($item->serialisedItem->asset_number ?? $item->serialised_item_id),
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

    /** @return array<string, list<string>> */
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
                $storeName = $target->store_id !== null
                    ? $target->store->name
                    : $target->serialisedItem->store?->name;
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

    /** @return array<string, mixed> */
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
                && $this->container->product_id !== null
                && ($this->container->product->container_checkin_mode ?? ContainerCheckinMode::Parent) !== ContainerCheckinMode::Individual,
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

    public function render(): View
    {
        return view('containers::show', $this->with());
    }
}

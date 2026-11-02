<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Livewire;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\PackContainerItem;
use App\Domain\Warehouse\Containers\Actions\SealContainer;
use App\Domain\Warehouse\Containers\Actions\UnpackContainerItem;
use App\Domain\Warehouse\Containers\Data\PackContainerItemData;
use App\Domain\Warehouse\Containers\Data\UnpackContainerItemData;
use App\Domain\Warehouse\Containers\Enums\ContainerItemUnpackReason;
use App\Domain\Warehouse\Containers\Enums\ContainerScanMode;
use App\Domain\Warehouse\Containers\Exceptions\ContainerPackConflictException;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Services\ContainerManifestService;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Pack extends Component
{
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

    /** @return array<string, mixed> */
    public function with(): array
    {
        $compliance = app(ContainerManifestService::class)->compliance($this->container);
        $productIds = collect($compliance->slots)->pluck('product_id')->unique()->all();
        $productNames = Product::query()->whereIn('id', $productIds)->pluck('name', 'id');

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

    public function render(): View
    {
        return view('containers::pack', $this->with());
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Livewire;

use App\Domain\Products\Models\Product;
use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Projects\Opportunities\Models\OpportunityItemAsset;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Warehouse\Containers\Actions\RepackReturnItem;
use App\Domain\Warehouse\Containers\Actions\SealContainer;
use App\Domain\Warehouse\Containers\Data\RepackReturnItemData;
use App\Domain\Warehouse\Containers\Enums\ContainerScanMode;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Services\ContainerManifestService;
use App\Enums\AssetAssignmentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Repack extends Component
{
    public Container $container;

    public Opportunity $opportunity;

    public string $scanInput = '';

    public bool $freeScan = false;

    public function mount(Container $container, Opportunity $opportunity): void
    {
        Gate::authorize('view', $container);
        Gate::authorize('view', $opportunity);

        if ($container->previous_container_id === null) {
            abort(404);
        }

        if (! $container->status->acceptsPacking()) {
            abort(403);
        }

        $this->container = $container->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
        $this->opportunity = $opportunity;
    }

    public function scanReturnItem(): void
    {
        Gate::authorize('return', $this->container);
        Gate::authorize('pack', $this->container);

        $stockLevel = $this->resolveStockLevel(trim($this->scanInput));

        if ($stockLevel === null) {
            $this->addError('scanInput', __('No serialised asset matched that scan.'));

            return;
        }

        (new RepackReturnItem)($this->container, RepackReturnItemData::from([
            'opportunity_id' => $this->opportunity->id,
            'serialised_item_id' => $stockLevel->id,
            'free_scan' => $this->freeScan,
        ]));

        $this->scanInput = '';
        $this->resetErrorBag('scanInput');
        $this->container->refresh()->load(['product', 'store', 'activeItems.product', 'activeItems.serialisedItem']);
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

    /**
     * Assets still out on the opportunity (left panel).
     *
     * @return Collection<int, OpportunityItemAsset>
     */
    public function outstandingAssets(): Collection
    {
        return OpportunityItemAsset::query()
            ->with(['stockLevel', 'item'])
            ->whereHas('item', fn ($q) => $q->where('opportunity_id', $this->opportunity->id))
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched,
                AssetAssignmentStatus::OnHire,
            ])
            ->orderBy('id')
            ->get();
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
            'outstanding' => $this->outstandingAssets(),
            'canSeal' => Gate::check('seal', $this->container)
                && $this->container->activeItems()->count() > 0
                && ($this->container->scan_mode !== ContainerScanMode::Strict || $compliance->compliant),
            'isStrict' => $this->container->scan_mode === ContainerScanMode::Strict,
        ];
    }

    public function render(): View
    {
        return view('containers::repack', $this->with());
    }
}

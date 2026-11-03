<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Presentation\Livewire;

use App\Domain\Projects\Opportunities\Models\Opportunity;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Domain\Warehouse\Containers\Actions\CreateContainer;
use App\Domain\Warehouse\Containers\Actions\StartRepackOnReturn;
use App\Domain\Warehouse\Containers\Data\CreateContainerData;
use App\Domain\Warehouse\Containers\Data\StartRepackOnReturnData;
use App\Domain\Warehouse\Containers\Enums\ContainerAvailabilityMode;
use App\Domain\Warehouse\Containers\Enums\ContainerStatus;
use App\Domain\Warehouse\Containers\Models\Container;
use App\Domain\Warehouse\Containers\Services\ContainerBarcodeResolver;
use App\Domain\Warehouse\Containers\Services\RepackOnReturnEligibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Containers')]
final class Index extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'mode')]
    public string $modeFilter = '';

    #[Url]
    public string $search = '';

    public string $scanBarcode = '';

    public ?int $pendingRepackHousingId = null;

    /** @var array<int, string> */
    public array $repackOpportunityChoices = [];

    public ?int $selectedRepackOpportunityId = null;

    /** @var Collection<int, Store>|null */
    private ?Collection $storesCache = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Container::class);
    }

    public function setStatusFilter(string $status): void
    {
        if ($status !== '' && ContainerStatus::tryFrom($status) === null) {
            return;
        }

        $this->statusFilter = $status;
    }

    public function setStoreFilter(string $storeId): void
    {
        $this->storeFilter = $storeId;
    }

    public function setModeFilter(string $mode): void
    {
        if ($mode !== '' && ContainerAvailabilityMode::tryFrom($mode) === null) {
            return;
        }

        $this->modeFilter = $mode;
    }

    public function submitScan(): void
    {
        $resolution = app(ContainerBarcodeResolver::class)->resolve($this->scanBarcode);

        if ($resolution === null) {
            $this->addError('scanBarcode', __('No container or containerable asset matched that barcode.'));

            return;
        }

        if ($resolution['type'] === 'container') {
            $this->redirect(route('containers.show', $resolution['container']), navigate: true);

            return;
        }

        /** @var StockLevel $stockLevel */
        $stockLevel = $resolution['stock_level'];

        if ($resolution['container'] !== null) {
            $this->redirect(route('containers.show', $resolution['container']), navigate: true);

            return;
        }

        if ($this->tryStartRepackFromReturn($stockLevel)) {
            return;
        }

        if (! Gate::check('create', Container::class)) {
            $this->addError('scanBarcode', __('No active container exists for this housing and you cannot create one.'));

            return;
        }

        $container = (new CreateContainer)(CreateContainerData::from([
            'serialised_item_id' => $stockLevel->id,
        ]));

        $this->scanBarcode = '';
        $this->redirect(route('containers.pack', $container->uuid), navigate: true);
    }

    protected function tryStartRepackFromReturn(StockLevel $stockLevel): bool
    {
        $eligibility = app(RepackOnReturnEligibility::class);

        if (! $eligibility->isEligibleHousing($stockLevel)) {
            return false;
        }

        $opportunities = $eligibility->checkedOutOpportunities($stockLevel);

        if ($opportunities->isEmpty()) {
            return false;
        }

        if (! Gate::check('create', Container::class) || ! Gate::check('opportunities.edit')) {
            $this->addError('scanBarcode', __('You do not have permission to start a repack session.'));

            return true;
        }

        if ($opportunities->count() === 1) {
            $this->startRepackFromReturn($stockLevel->id, $opportunities->first()->id);

            return true;
        }

        $this->pendingRepackHousingId = $stockLevel->id;
        $this->repackOpportunityChoices = $opportunities
            ->mapWithKeys(fn (Opportunity $opportunity): array => [
                $opportunity->id => trim(($opportunity->reference ?: '#'.$opportunity->id).' — '.$opportunity->subject),
            ])
            ->all();
        $this->selectedRepackOpportunityId = $opportunities->first()->id;
        $this->dispatch('open-modal', 'repack-opportunity-picker');

        return true;
    }

    public function confirmRepackFromReturn(): void
    {
        if ($this->pendingRepackHousingId === null || $this->selectedRepackOpportunityId === null) {
            return;
        }

        Gate::authorize('create', Container::class);
        Gate::authorize('opportunities.edit');

        $this->startRepackFromReturn($this->pendingRepackHousingId, $this->selectedRepackOpportunityId);
    }

    public function dismissRepackOpportunityPicker(): void
    {
        $this->pendingRepackHousingId = null;
        $this->repackOpportunityChoices = [];
        $this->selectedRepackOpportunityId = null;
        $this->dispatch('close-modal', 'repack-opportunity-picker');
    }

    protected function startRepackFromReturn(int $housingId, int $opportunityId): void
    {
        $result = (new StartRepackOnReturn)(StartRepackOnReturnData::from([
            'opportunity_id' => $opportunityId,
            'serialised_item_id' => $housingId,
        ]));

        $this->scanBarcode = '';
        $this->dismissRepackOpportunityPicker();

        $this->redirect(route('containers.repack', [
            'container' => $result->container->uuid,
            'opportunity' => $opportunityId,
        ]), navigate: true);
    }

    /** @return LengthAwarePaginator<int, Container> */
    public function containers(): LengthAwarePaginator
    {
        return Container::query()
            ->with(['product', 'serialisedItem.store'])
            ->withCount('activeItems')
            ->when($this->statusFilter === '', fn ($q) => $q->active())
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->storeFilter !== '', fn ($q) => $q->where('store_id', (int) $this->storeFilter))
            ->when($this->modeFilter !== '', function ($q): void {
                $q->whereHas('product', fn ($inner) => $inner->where('container_availability_mode', $this->modeFilter));
            })
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('name', 'ilike', $term)
                        ->orWhere('barcode', 'ilike', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'statuses' => ContainerStatus::cases(),
            'modes' => ContainerAvailabilityMode::cases(),
            'stores' => $this->storesCache ??= Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function render(): View
    {
        return view('containers::index', $this->with());
    }
}

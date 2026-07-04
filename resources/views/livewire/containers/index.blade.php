<?php

use App\Actions\Containers\CreateContainer;
use App\Actions\Containers\StartRepackOnReturn;
use App\Data\Containers\CreateContainerData;
use App\Data\Containers\StartRepackOnReturnData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Opportunity;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Containers\ContainerBarcodeResolver;
use App\Services\Containers\RepackOnReturnEligibility;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Containers')] class extends Component {
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

        /** @var \App\Models\StockLevel $stockLevel */
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

    public function containers()
    {
        return Container::query()
            ->with(['product', 'store', 'serialisedItem.store'])
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

    public function with(): array
    {
        return [
            'statuses' => ContainerStatus::cases(),
            'modes' => ContainerAvailabilityMode::cases(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Containers">
        <x-slot:meta>
            <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Stock</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.card class="mb-4">
            <form wire:submit="submitScan" class="flex flex-wrap items-end gap-3">
                <flux:field class="min-w-[16rem] flex-1">
                    <flux:label>Scan barcode</flux:label>
                    <flux:input wire:model="scanBarcode" class="font-mono" placeholder="Container barcode or housing asset number…" autocomplete="off" />
                </flux:field>
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary">Go</flux:button>
            </form>
            @error('scanBarcode')
                <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </x-signals.card>

        <x-signals.modal name="repack-opportunity-picker" title="{{ __('Start repack from return') }}" size="md" :teleport="false">
            <p class="text-sm text-[var(--muted)] mb-4">{{ __('This housing is checked out on multiple opportunities. Choose which return to process.') }}</p>
            <flux:field>
                <flux:label>{{ __('Opportunity') }}</flux:label>
                <flux:select wire:model="selectedRepackOpportunityId">
                    @foreach($repackOpportunityChoices as $id => $label)
                        <option value="{{ $id }}" wire:key="repack-opp-{{ $id }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
            <div class="mt-4 flex flex-wrap gap-2 justify-end">
                <flux:button type="button" wire:click="dismissRepackOpportunityPicker">{{ __('Cancel') }}</flux:button>
                <flux:button type="button" wire:click="confirmRepackFromReturn" wire:loading.attr="disabled" variant="primary">
                    {{ __('Start repack session') }}
                </flux:button>
            </div>
        </x-signals.modal>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by name or barcode..." class="max-w-sm" />
            <flux:select wire:model.live="storeFilter" class="max-w-[10rem]">
                <option value="">All stores</option>
                @foreach($stores as $store)
                    <option value="{{ $store->id }}" wire:key="store-opt-{{ $store->id }}">{{ $store->name }}</option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setModeFilter('')" class="s-chip {{ $modeFilter === '' ? 's-chip-active' : '' }}">All modes</button>
                @foreach($modes as $mode)
                    <button type="button" wire:click="setModeFilter('{{ $mode->value }}')" wire:key="mode-filter-{{ $mode->value }}" class="s-chip {{ $modeFilter === $mode->value ? 's-chip-active' : '' }}">{{ $mode->label() }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 's-chip-active' : '' }}">Active</button>
                @foreach($statuses as $status)
                    <button type="button" wire:click="setStatusFilter('{{ $status->value }}')" wire:key="status-filter-{{ $status->value }}" class="s-chip {{ $statusFilter === $status->value ? 's-chip-active' : '' }}">{{ $status->label() }}</button>
                @endforeach
            </div>
        </div>

        <x-signals.card>
            <x-signals.table-wrap>
                <table class="s-table">
                    <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Mode</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th class="text-right">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->containers() as $container)
                            <tr wire:key="container-row-{{ $container->uuid }}">
                                <td style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('containers.show', $container) }}" wire:navigate class="font-medium text-[var(--link)] hover:underline">
                                        {{ $container->name }}
                                    </a>
                                </td>
                                <td><span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span></td>
                                <td>{{ $container->availabilityMode()->label() }}</td>
                                <td>{{ $container->store?->name ?? $container->serialisedItem?->store?->name ?? '—' }}</td>
                                <td>{{ $container->is_temporary ? 'Temporary' : 'Product-backed' }}</td>
                                <td class="text-right" style="font-family: var(--font-mono);">{{ $container->active_items_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-[var(--muted)] py-8">No containers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-signals.table-wrap>

            <div class="mt-4">
                {{ $this->containers()->links() }}
            </div>
        </x-signals.card>
    </div>
</section>

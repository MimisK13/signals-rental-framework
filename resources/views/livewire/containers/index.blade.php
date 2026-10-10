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
use Illuminate\Support\Collection;
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

    public function with(): array
    {
        return [
            'statuses' => ContainerStatus::cases(),
            'modes' => ContainerAvailabilityMode::cases(),
            'stores' => $this->storesCache ??= Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<section class="w-full" data-density="compact">
    <x-dense::page-header title="Containers">
        <x-slot:meta>
            <span>Physical warehouse units</span>
        </x-slot:meta>
        <x-slot:actions>
            @can('create', Container::class)
                <flux:button :href="route('containers.create')" variant="primary" size="sm" wire:navigate.hover>
                    New durable Container
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-dense::page-header>

    <div class="flex-1 space-y-4 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-dense::toolbar class="w-full flex-wrap items-end !gap-3 !px-0 !py-0" data-density="comfortable">
            <form wire:submit="submitScan" class="flex w-full items-end gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-1">
                    <x-dense::field-label for="container-scan-barcode">{{ __('Scan barcode') }}</x-dense::field-label>
                    <x-dense::search-input model="scanBarcode" placeholder="Container barcode or housing asset number…" class="w-full" />
                    </div>
                </div>
                <flux:button type="submit" wire:loading.attr="disabled" variant="primary" size="sm" class="!h-[var(--erp-control-height,40px)]">{{ __('Go') }}</flux:button>
            </form>
            @error('scanBarcode')
                <p class="w-full text-sm text-[var(--danger)]">{{ $message }}</p>
            @enderror
        </x-dense::toolbar>

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

        <x-dense::toolbar class="w-full flex-wrap items-end justify-between !gap-3 !px-0 !py-0">
            <div class="min-w-52 flex-1 sm:max-w-80">
                <div class="flex flex-col gap-1">
                    <x-dense::field-label for="container-search">{{ __('Search') }}</x-dense::field-label>
                    <x-dense::search-input id="container-search" model="search" placeholder="Name or barcode" />
                </div>
            </div>
            <div class="min-w-52 flex-1 sm:w-64 sm:flex-none">
                <div class="flex flex-col gap-1">
                    <x-dense::field-label>{{ __('Store') }}</x-dense::field-label>
                    <x-dense::store-picker
                        :value="$storeFilter !== '' && $stores->firstWhere('id', (int) $storeFilter) ? [$stores->firstWhere('id', (int) $storeFilter)->name] : []"
                        :suggestions="$stores->pluck('name')->all()"
                        single
                        float-suggestions
                        placeholder="{{ __('All stores') }}"
                        class="w-full"
                        x-on:tags-changed="$wire.set('storeFilter', $event.detail[0] ? {{ \Illuminate\Support\Js::from($stores->mapWithKeys(fn ($store): array => [$store->name => (string) $store->id])->all()) }}[$event.detail[0]] : '')"
                    />
                </div>
            </div>
        </x-dense::toolbar>

        <div class="flex flex-wrap items-center gap-4 text-xs text-[var(--text-muted)]">
            <nav class="flex flex-wrap items-center gap-1" aria-label="Container status">
                <span class="mr-1 font-medium text-[var(--text)]">{{ __('Status') }}</span>
                <button type="button" wire:click="setStatusFilter('')" class="s-chip {{ $statusFilter === '' ? 's-chip-active' : '' }}">Active</button>
                @foreach($statuses as $status)
                    <button type="button" wire:click="setStatusFilter('{{ $status->value }}')" wire:key="status-filter-{{ $status->value }}" class="s-chip {{ $statusFilter === $status->value ? 's-chip-active' : '' }}">{{ $status->label() }}</button>
                @endforeach
            </nav>
            <nav class="flex flex-wrap items-center gap-1" aria-label="Container mode">
                <span class="mr-1 font-medium text-[var(--text)]">{{ __('Mode') }}</span>
                <button type="button" wire:click="setModeFilter('')" class="s-chip {{ $modeFilter === '' ? 's-chip-active' : '' }}">All</button>
                @foreach($modes as $mode)
                    <button type="button" wire:click="setModeFilter('{{ $mode->value }}')" wire:key="mode-filter-{{ $mode->value }}" class="s-chip {{ $modeFilter === $mode->value ? 's-chip-active' : '' }}">{{ $mode->label() }}</button>
                @endforeach
            </nav>
        </div>

        @php($containers = $this->containers())
        <x-dense::table-card title="Containers" :count="$containers->total()" count-class="s-badge-blue">
            @if($containers->isEmpty())
                <x-dense::empty-state title="No containers found" description="No containers match the current filters." />
            @else
                <table class="s-table s-table-compact w-full">
                    <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Mode</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th class="text-right">Items</th>
                            <th class="text-center"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($containers as $container)
                            <tr wire:key="container-row-{{ $container->uuid }}">
                                <td style="font-family: var(--font-mono);">{{ $container->barcode ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('containers.show', $container) }}" wire:navigate.hover class="font-medium text-[var(--link)] hover:underline">
                                        {{ $container->name }}
                                    </a>
                                </td>
                                <td><span class="s-badge s-badge-zinc">{{ $container->status->label() }}</span></td>
                                <td>{{ $container->availabilityMode()->label() }}</td>
                                <td>{{ $stores->firstWhere('id', (int) $container->store_id)?->name ?? $container->serialisedItem?->store?->name ?? '—' }}</td>
                                <td>{{ $container->is_temporary ? 'Temporary' : 'Product-backed' }}</td>
                                <td class="text-right font-mono">{{ $container->active_items_count }}</td>
                                <td class="text-center">
                                    <div class="flex justify-center">
                                        <div x-data="{ open: false }" x-on:keydown.escape.prevent.stop="open = false" class="relative inline-flex">
                                            <button
                                                x-ref="button"
                                                type="button"
                                                class="s-btn-ghost s-btn-xs s-btn-icon"
                                                aria-label="Container actions"
                                                title="Container actions"
                                                :aria-expanded="open"
                                                x-on:click="open = ! open"
                                            >
                                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                                                    <circle cx="12" cy="5" r="1.5" />
                                                    <circle cx="12" cy="12" r="1.5" />
                                                    <circle cx="12" cy="19" r="1.5" />
                                                </svg>
                                            </button>
                                            <div
                                                x-ref="panel"
                                                x-show="open"
                                                x-cloak
                                                x-on:click.outside="open = false"
                                                class="s-dropdown"
                                                style="position: fixed; z-index: 9999; min-width: 150px;"
                                                x-init="$watch('open', value => {
                                                    if (value) {
                                                        $nextTick(() => {
                                                            const rect = $refs.button.getBoundingClientRect();
                                                            $el.style.top = rect.bottom + 4 + 'px';
                                                            $el.style.right = (window.innerWidth - rect.right) + 'px';
                                                            $el.style.left = 'auto';
                                                        });
                                                    }
                                                })"
                                            >
                                                <a href="{{ route('containers.show', $container) }}" wire:navigate.hover x-on:click="open = false">{{ __('Open') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <x-slot:footer>
                {{ $containers->links() }}
            </x-slot:footer>
        </x-dense::table-card>
    </div>
</section>

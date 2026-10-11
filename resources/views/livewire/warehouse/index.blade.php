<?php

use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Warehouse')] class extends Component
{
    #[Url(as: 'store')]
    public string $storeFilter = '';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    #[Url]
    public string $search = '';

    /** @var Collection<int, Store>|null */
    private ?Collection $storesCache = null;

    public function mount(): void
    {
        Gate::authorize('opportunities.view');
    }

    public function resetFilters(): void
    {
        $this->storeFilter = '';
        $this->from = '';
        $this->until = '';
        $this->search = '';
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'stores' => $this->storesCache ??= $this->stores(),
            'columns' => [
                ['key' => 'number', 'label' => 'Order', 'sortable' => true, 'view' => 'livewire.warehouse.partials.column-order'],
                ['key' => 'member_id', 'label' => 'Customer', 'view' => 'livewire.warehouse.partials.column-customer'],
                ['key' => 'store_id', 'label' => 'Store', 'view' => 'livewire.warehouse.partials.column-store'],
                ['key' => 'planning_starts_at', 'label' => 'Planning period', 'sortable' => true, 'view' => 'livewire.warehouse.partials.column-period'],
                ['key' => 'items_count', 'label' => 'Lines', 'sortable' => true, 'view' => 'livewire.warehouse.partials.column-lines'],
                ['key' => 'status', 'label' => 'Status', 'view' => 'livewire.warehouse.partials.column-status'],
                ['key' => 'has_shortage', 'label' => 'Attention', 'view' => 'livewire.warehouse.partials.column-attention'],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => [
                'warehouseQueue' => [
                    'accessible_store_ids' => auth()->user()->accessibleStoreIds(),
                    'store' => $this->storeFilter,
                    'from' => $this->from,
                    'until' => $this->until,
                    'search' => $this->search,
                ],
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function stores(): Collection
    {
        if ($this->storesCache !== null) {
            return $this->storesCache;
        }

        $query = Store::query()->orderBy('name');
        $accessibleStoreIds = auth()->user()->accessibleStoreIds();

        if ($accessibleStoreIds !== null) {
            $query->whereIn('id', $accessibleStoreIds);
        }

        return $this->storesCache = $query->get(['id', 'name']);
    }
}; ?>

<section class="w-full" data-density="compact">
    <x-dense::page-header title="Warehouse">
        <x-slot:meta>
            <span>Operational work queue</span>
        </x-slot:meta>
    </x-dense::page-header>

    <div class="flex-1 space-y-4 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-dense::toolbar class="w-full flex-wrap items-end justify-between !gap-3 !px-0 !py-0">
            <div class="min-w-44 flex-1 sm:max-w-72">
                <div class="flex flex-col gap-1">
                    <x-dense::field-label>{{ __('Search') }}</x-dense::field-label>
                    <x-dense::search-input model="search" placeholder="Order, customer or reference" />
                </div>
            </div>
            <div class="flex w-full flex-wrap items-end justify-end gap-3 sm:w-auto">
                <div class="min-w-44 flex-1 sm:w-64 sm:flex-none">
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label>{{ __('Store') }}</x-dense::field-label>
                        <x-dense::store-picker
                            :value="$storeFilter !== '' && $stores->firstWhere('id', (int) $storeFilter) ? [$stores->firstWhere('id', (int) $storeFilter)->name] : []"
                            :suggestions="$stores->pluck('name')->all()"
                            single
                            placeholder="{{ __('All stores') }}"
                            class="w-full"
                            x-on:tags-changed="$wire.set('storeFilter', $event.detail[0] ? {{ \Illuminate\Support\Js::from($stores->mapWithKeys(fn ($store): array => [$store->name => (string) $store->id])->all()) }}[$event.detail[0]] : '')"
                        />
                    </div>
                </div>
                <div class="w-40 flex-1 sm:flex-none">
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label>{{ __('From') }}</x-dense::field-label>
                        <x-dense::date-picker
                            :value="$from !== '' ? $from : null"
                            placeholder="{{ __('Pick a date') }}"
                            floating
                            class="w-full"
                            x-on:input="$wire.set('from', $event.detail)"
                        />
                    </div>
                </div>
                <div class="w-40 flex-1 sm:flex-none">
                    <div class="flex flex-col gap-1">
                        <x-dense::field-label>{{ __('Until') }}</x-dense::field-label>
                        <x-dense::date-picker
                            :value="$until !== '' ? $until : null"
                            placeholder="{{ __('Pick a date') }}"
                            floating
                            class="w-full"
                            x-on:input="$wire.set('until', $event.detail)"
                        />
                    </div>
                </div>
            </div>
        </x-dense::toolbar>

        <!-- TODO: Snipe this preserved queue-based Warehouse UI back into the
             DataTable workflow if the operational queue presentation is needed again.
             It is intentionally retained below while DataTable is the active listing. -->
        @if (false)
        <nav
            class="flex flex-wrap items-center gap-1 text-xs text-[var(--text-muted)]"
            aria-label="Warehouse queues"
            x-data="{ activeQueue: 'preparation' }"
        >
            @foreach ($sections as $key => $section)
                <a
                    href="#warehouse-{{ \Illuminate\Support\Str::slug($section['label']) }}"
                    class="s-chip"
                    :class="{ 'on': activeQueue === '{{ $key }}' }"
                    :aria-current="activeQueue === '{{ $key }}' ? 'page' : null"
                    x-on:click="activeQueue = '{{ $key }}'"
                >
                    {{ $section['label'] }} <span class="s-chip-count font-mono">{{ $section['count'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="space-y-4">
            @foreach ($sections as $key => $section)
                <x-dense::table-card
                    id="warehouse-{{ \Illuminate\Support\Str::slug($section['label']) }}"
                    :title="$section['label']"
                    :count="$section['count']"
                    :count-class="$section['badge_class']"
                    collapsible
                >

                    @if ($section['rows'] === [])
                        <x-dense::empty-state title="Nothing in this queue" description="No orders match the current filters." />
                    @else
                        <x-dense::table compact class="w-full">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Store</th>
                                        <th>Planning period</th>
                                        <th>Lines</th>
                                        <th>Attention</th>
                                        <th class="text-center"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section['rows'] as $row)
                                        <tr wire:key="warehouse-{{ $key }}-{{ $row['id'] }}">
                                            <td>
                                                <a href="{{ $row['assets_url'] }}" class="font-medium text-[var(--text)] hover:text-[var(--blue)]" wire:navigate.hover>
                                                    {{ $row['number'] }}
                                                </a>
                                                @if ($row['reference'])
                                                    <div class="text-xs text-[var(--text-muted)]">{{ $row['reference'] }}</div>
                                                @endif
                                                <div class="text-xs text-[var(--text-muted)]">{{ $row['subject'] }}</div>
                                            </td>
                                            <td>{{ $row['customer'] }}</td>
                                            <td>{{ $row['store'] }}</td>
                                            <td class="whitespace-nowrap">{{ $row['period'] }}</td>
                                            <td class="tabular-nums">{{ $row['items_count'] }}</td>
                                            <td>
                                                @if ($row['has_shortage'])
                                                <span class="s-badge s-badge-red">Shortage</span>
                                                @else
                                                    <span class="text-xs text-[var(--text-muted)]">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="flex justify-center">
                                                    <div
                                                        x-data="{
                                                            open: false,
                                                            toggle() {
                                                                if (this.open) {
                                                                    return this.close()
                                                                }

                                                                this.$refs.button.focus()
                                                                this.open = true
                                                            },
                                                            close(focusAfter) {
                                                                if (! this.open) return

                                                                this.open = false
                                                                focusAfter && focusAfter.focus()
                                                            }
                                                        }"
                                                        x-on:keydown.escape.prevent.stop="close($refs.button)"
                                                        x-on:focusin.window="! $refs.panel.contains($event.target) && close()"
                                                        x-id="['warehouse-row-actions']"
                                                        class="relative inline-flex"
                                                    >
                                                        <button
                                                            x-ref="button"
                                                            x-on:click.stop="toggle()"
                                                            :aria-expanded="open"
                                                            :aria-controls="$id('warehouse-row-actions')"
                                                            type="button"
                                                            class="s-btn-ghost s-btn-xs s-btn-icon"
                                                            aria-label="Order actions"
                                                            title="Order actions"
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
                                                            x-transition.origin.top.right
                                                            x-on:click.outside="close($refs.button)"
                                                            :id="$id('warehouse-row-actions')"
                                                            x-cloak
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
                                                            <a href="{{ $row['assets_url'] }}" wire:navigate.hover x-on:click="close()" class="s-dropdown-item">
                                                                {{ $section['action_label'] }}
                                                            </a>
                                                            <a href="{{ $row['show_url'] }}" wire:navigate.hover x-on:click="close()" class="s-dropdown-item">
                                                                Open
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                        </x-dense::table>
                        @if ($section['count'] > count($section['rows']))
                            <x-slot:footer>
                                <span class="text-xs text-[var(--text-muted)]">Showing {{ count($section['rows']) }} of {{ $section['count'] }} orders.</span>
                            </x-slot:footer>
                        @endif
                    @endif
                </x-dense::table-card>
            @endforeach
        </div>
        @endif

        <livewire:components.data-table
            :columns="$columns"
            :model="\App\Models\Opportunity::class"
            :searchable="[]"
            :with="['member', 'store']"
            :with-counts="['items']"
            :scopes="$scopes"
            default-sort="planning_starts_at"
            default-direction="asc"
            empty-message="No orders match the current warehouse filters."
            actions-view="livewire.warehouse.partials.row-actions"
            :key="'warehouse-table-'.$storeFilter.'-'.$from.'-'.$until.'-'.md5($search)"
        />
    </div>
</section>

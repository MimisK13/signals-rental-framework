<?php

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            'sections' => $this->sections(),
            'stores' => $this->stores(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            'preparation' => $this->section(
                'Needs preparation',
                'Confirmed orders waiting for warehouse preparation.',
                [OpportunityStatus::OrderActive],
                'Prepare',
                's-badge-amber',
            ),
            'going_out' => $this->section(
                'Ready / going out',
                'Orders that have entered dispatch.',
                [OpportunityStatus::OrderDispatched],
                'Open execution',
                's-badge-blue',
            ),
            'on_hire' => $this->section(
                'On hire',
                'Orders currently out with the customer or on site.',
                [OpportunityStatus::OrderOnHire],
                'Check in',
                's-badge-violet',
            ),
            'returns' => $this->section(
                'Returns / checking',
                'Returned orders waiting for checking or finalisation.',
                [OpportunityStatus::OrderReturned, OpportunityStatus::OrderChecked],
                'Open',
                's-badge-green',
            ),
        ];
    }

    /**
     * @param  list<OpportunityStatus>  $statuses
     * @return array<string, mixed>
     */
    private function section(
        string $label,
        string $description,
        array $statuses,
        string $actionLabel,
        string $badgeClass,
    ): array {
        $query = $this->opportunitiesQuery($statuses);
        $count = (clone $query)->count();
        $opportunities = $query->limit(25)->get();

        return [
            'label' => $label,
            'description' => $description,
            'count' => $count,
            'badge_class' => $badgeClass,
            'action_label' => $actionLabel,
            'rows' => $opportunities->map(fn (Opportunity $opportunity): array => $this->row($opportunity))->all(),
        ];
    }

    /**
     * @param  list<OpportunityStatus>  $statuses
     */
    private function opportunitiesQuery(array $statuses): Builder
    {
        $query = Opportunity::query()
            ->where('state', OpportunityState::Order->value)
            ->whereIn('status', array_map(
                static fn (OpportunityStatus $status): int => $status->statusValue(),
                $statuses,
            ))
            ->with(['member:id,name', 'store:id,name'])
            ->withCount('items')
            ->orderBy('planning_starts_at')
            ->orderBy('starts_at');

        $accessibleStoreIds = auth()->user()->accessibleStoreIds();

        if ($accessibleStoreIds !== null) {
            $query->whereIn('store_id', $accessibleStoreIds);
        }

        if ($this->storeFilter !== '') {
            $query->where('store_id', (int) $this->storeFilter);
        }

        $from = $this->dateFilter($this->from);
        $until = $this->dateFilter($this->until);

        if ($from !== null) {
            $query->where(function (Builder $inner) use ($from): void {
                $inner->whereNull('planning_ends_at')
                    ->orWhereDate('planning_ends_at', '>=', $from->toDateString());
            });
        }

        if ($until !== null) {
            $query->where(function (Builder $inner) use ($until): void {
                $inner->whereNull('planning_starts_at')
                    ->orWhereDate('planning_starts_at', '<=', $until->toDateString());
            });
        }

        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('number', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhereHas('member', fn (Builder $member): Builder => $member->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Opportunity $opportunity): array
    {
        $status = $opportunity->statusEnum();

        return [
            'id' => $opportunity->id,
            'number' => $opportunity->number ?: '#'.$opportunity->id,
            'reference' => $opportunity->reference,
            'subject' => $opportunity->subject,
            'customer' => $opportunity->member?->name ?: 'No customer',
            'store' => $opportunity->store?->name ?: 'No store',
            'items_count' => $opportunity->items_count,
            'period' => $this->periodLabel($opportunity),
            'status' => $status->label(),
            'status_class' => match ($status) {
                OpportunityStatus::OrderActive => 's-badge-amber',
                OpportunityStatus::OrderDispatched => 's-badge-blue',
                OpportunityStatus::OrderOnHire => 's-badge-violet',
                default => 's-badge-green',
            },
            'has_shortage' => (bool) $opportunity->has_shortage,
            'assets_url' => route('opportunities.assets', $opportunity),
            'show_url' => route('opportunities.show', $opportunity),
        ];
    }

    private function periodLabel(Opportunity $opportunity): string
    {
        $start = $opportunity->planning_starts_at ?? $opportunity->starts_at;
        $end = $opportunity->planning_ends_at ?? $opportunity->ends_at;

        if ($start === null && $end === null) {
            return 'No period';
        }

        if ($start === null) {
            return 'Until '.$end->format('d M Y');
        }

        if ($end === null || $start->isSameDay($end)) {
            return $start->format('d M Y');
        }

        return $start->format('d M').' – '.$end->format('d M Y');
    }

    private function dateFilter(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Store>
     */
    private function stores()
    {
        $query = Store::query()->orderBy('name');
        $accessibleStoreIds = auth()->user()->accessibleStoreIds();

        if ($accessibleStoreIds !== null) {
            $query->whereIn('id', $accessibleStoreIds);
        }

        return $query->get(['id', 'name']);
    }
}; ?>

<section class="w-full" data-density="compact">
    <x-signals.page-header title="Warehouse">
        <x-slot:meta>
            <span>Operational work queue</span>
        </x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 space-y-4 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.toolbar class="w-full flex-wrap items-end justify-between !gap-3 !px-0 !py-0">
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
        </x-signals.toolbar>

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
                <x-signals.table-card
                    id="warehouse-{{ \Illuminate\Support\Str::slug($section['label']) }}"
                    :title="$section['label']"
                    :count="$section['count']"
                    :count-class="$section['badge_class']"
                    collapsible
                >

                    @if ($section['rows'] === [])
                        <x-signals.empty title="Nothing in this queue" description="No orders match the current filters." />
                    @else
                        <table class="s-table s-table-compact w-full">
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
                        </table>
                        @if ($section['count'] > count($section['rows']))
                            <x-slot:footer>
                                <span class="text-xs text-[var(--text-muted)]">Showing {{ count($section['rows']) }} of {{ $section['count'] }} orders.</span>
                            </x-slot:footer>
                        @endif
                    @endif
                </x-signals.table-card>
            @endforeach
        </div>
    </div>
</section>

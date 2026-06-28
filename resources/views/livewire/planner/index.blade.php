<?php

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Calendar\EventLaneAllocator;
use App\Support\Formatter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Job Planner (C1) — a calendar of OPPORTUNITIES (jobs) across dates.
 *
 * Overlap timeline: concurrent jobs are packed into horizontal lanes via
 * {@see EventLaneAllocator}. Each job draws a bar spanning its window with
 * Delivery / In-Use / Collection sub-bands and Customer Collecting / Returning
 * badges, coloured by document state (Order / Quote).
 */
new #[Layout('components.layouts.app')] #[Title('Job Planner')] class extends Component
{
    private const int JOBS_LIMIT = 200;

    /** Selectable view-period lengths, in days, keyed by the URL token. */
    private const VIEW_PERIODS = [
        '1w' => 7,
        '2w' => 14,
        '1m' => 30,
    ];

    /** The window start (ISO date). Defaults to today on mount. */
    #[Url(as: 'date')]
    public string $date = '';

    /** View period token: 1w / 2w / 1m. Sets the column count unless a preset is active. */
    #[Url(as: 'view')]
    public string $viewPeriod = '2w';

    /** Active date-range preset (next_30_days, this_month, next_month) or empty for custom/zoom. */
    #[Url(as: 'preset')]
    public string $preset = '';

    /** Whether quotation-state jobs are included. */
    #[Url(as: 'quotations')]
    public bool $includeQuotations = true;

    /** Whether order-state jobs are included. */
    #[Url(as: 'orders')]
    public bool $includeOrders = true;

    /** Store filter (0 = all stores). */
    #[Url(as: 'store')]
    public int $storeId = 0;

    /** Free-text search across subject / number / member name. */
    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('opportunities.access');

        if ($this->date === '') {
            $this->date = Carbon::today('UTC')->toDateString();
        }

        if (! array_key_exists($this->viewPeriod, self::VIEW_PERIODS)) {
            $this->viewPeriod = '2w';
        }
    }

    public function rendering(View $view): void
    {
        $view->title(__('Job Planner'));
    }

    /** The inclusive number of days the window spans. */
    public function windowDays(): int
    {
        if ($this->preset === 'next_30_days') {
            return 30;
        }

        if ($this->preset === 'this_month' || $this->preset === 'next_month') {
            return $this->windowStart()->daysInMonth;
        }

        return self::VIEW_PERIODS[$this->viewPeriod] ?? 14;
    }

    /** The window start, normalised to the start of the day. */
    public function windowStart(): Carbon
    {
        return Carbon::parse($this->date, 'UTC')->startOfDay();
    }

    /** The window end (inclusive), at the end of the last day. */
    public function windowEnd(): Carbon
    {
        return $this->windowStart()->copy()->addDays($this->windowDays() - 1)->endOfDay();
    }

    /** Step the window backwards by one full period. */
    public function previousPeriod(): void
    {
        $this->preset = '';
        $this->date = $this->windowStart()->copy()->subDays($this->windowDays())->toDateString();
    }

    /** Step the window forwards by one full period. */
    public function nextPeriod(): void
    {
        $this->preset = '';
        $this->date = $this->windowStart()->copy()->addDays($this->windowDays())->toDateString();
    }

    /** Jump the window back to today. */
    public function today(): void
    {
        $this->preset = '';
        $this->date = Carbon::today('UTC')->toDateString();
    }

    /** Set the zoom window (1w / 2w / 1m). */
    public function setZoom(string $period): void
    {
        if (! array_key_exists($period, self::VIEW_PERIODS)) {
            return;
        }

        $this->viewPeriod = $period;
        $this->preset = '';
    }

    /** Apply a named date-range preset. */
    public function applyPreset(string $type): void
    {
        match ($type) {
            'next_30_days' => $this->applyNext30Days(),
            'this_month' => $this->applyThisMonth(),
            'next_month' => $this->applyNextMonth(),
            default => null,
        };
    }

    public function updatedDate(): void
    {
        $this->preset = '';
    }

    private function applyNext30Days(): void
    {
        $this->date = Carbon::today('UTC')->toDateString();
        $this->viewPeriod = '1m';
        $this->preset = 'next_30_days';
    }

    private function applyThisMonth(): void
    {
        $this->date = Carbon::today('UTC')->startOfMonth()->toDateString();
        $this->preset = 'this_month';
    }

    private function applyNextMonth(): void
    {
        $this->date = Carbon::today('UTC')->addMonthNoOverflow()->startOfMonth()->toDateString();
        $this->preset = 'next_month';
    }

    /**
     * Day-column headers across the window.
     *
     * @return list<Carbon>
     */
    public function dayColumns(): array
    {
        $days = [];
        $cursor = $this->windowStart();
        $end = $this->windowEnd();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->copy();
            $cursor->addDay();
        }

        return $days;
    }

    /** Stores for the filter dropdown. */
    #[Computed]
    public function stores(): Collection
    {
        return Store::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Base query for opportunities overlapping the window (filters applied).
     */
    private function jobsQuery(): Builder
    {
        $states = [];
        if ($this->includeQuotations) {
            $states[] = OpportunityState::Quotation->value;
        }
        if ($this->includeOrders) {
            $states[] = OpportunityState::Order->value;
        }

        $from = $this->windowStart();
        $to = $this->windowEnd();

        return Opportunity::query()
            ->with(['member:id,name', 'store:id,name', 'venue:id,name'])
            ->whereIn('state', $states)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from)
            ->when($this->storeId > 0, fn (Builder $q) => $q->where('store_id', $this->storeId))
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->whereRaw('lower(subject) like ?', [$term])
                        ->orWhereRaw('lower(number) like ?', [$term])
                        ->orWhereHas('member', fn (Builder $m) => $m->whereRaw('lower(name) like ?', [$term]));
                });
            })
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    /** Whether more than {@see JOBS_LIMIT} jobs match — the grid shows the first 200 only. */
    #[Computed]
    public function jobsTruncated(): bool
    {
        if ($this->includeQuotations === false && $this->includeOrders === false) {
            return false;
        }

        return $this->jobsQuery()->count() > self::JOBS_LIMIT;
    }

    /**
     * The opportunities overlapping the window, after applying filters.
     *
     * @return Collection<int, Opportunity>
     */
    #[Computed]
    public function jobs(): Collection
    {
        $states = [];
        if ($this->includeQuotations) {
            $states[] = OpportunityState::Quotation->value;
        }
        if ($this->includeOrders) {
            $states[] = OpportunityState::Order->value;
        }

        if ($states === []) {
            return collect();
        }

        return $this->jobsQuery()->limit(self::JOBS_LIMIT)->get();
    }

    /**
     * Per-day job counts (jobs active on each calendar day in the window).
     *
     * @return array<string, int>
     */
    #[Computed]
    public function jobCountsPerDay(): array
    {
        $counts = [];

        foreach ($this->dayColumns() as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();
            $counts[$day->toDateString()] = $this->jobs->filter(function (Opportunity $job) use ($dayStart, $dayEnd): bool {
                return $job->starts_at <= $dayEnd && $job->ends_at >= $dayStart;
            })->count();
        }

        return $counts;
    }

    /**
     * KPI summary: jobs, value, orders/quotes, peak day, lanes used.
     *
     * @return array{
     *     jobs: int,
     *     orders: int,
     *     quotes: int,
     *     total_value_minor: int,
     *     total_value_currency: string,
     *     peak_day_label: ?string,
     *     peak_count: int,
     *     lanes_used: int,
     * }
     */
    #[Computed]
    public function summary(): array
    {
        $orders = $this->jobs->where('state', OpportunityState::Order)->count();
        $quotes = $this->jobs->where('state', OpportunityState::Quotation)->count();

        $totalValueMinor = (int) $this->jobs->sum(fn (Opportunity $job): int => $job->deal_total ?? $job->charge_total);

        $currencies = $this->jobs->pluck('currency_code')->filter()->unique();
        $valueCurrency = $currencies->count() === 1
            ? (string) $currencies->first()
            : settings('company.base_currency', 'GBP');

        $peakDate = null;
        $peakCount = 0;
        foreach ($this->jobCountsPerDay as $date => $count) {
            if ($count > $peakCount) {
                $peakCount = $count;
                $peakDate = $date;
            }
        }

        $lanesUsed = 0;
        foreach ($this->rows as $row) {
            $lanesUsed = max($lanesUsed, (int) ($row['lane'] ?? 0) + 1);
        }

        return [
            'jobs' => $this->jobs->count(),
            'orders' => $orders,
            'quotes' => $quotes,
            'total_value_minor' => $totalValueMinor,
            'total_value_currency' => is_string($valueCurrency) && $valueCurrency !== '' ? $valueCurrency : 'GBP',
            'peak_day_label' => $peakDate !== null
                ? Carbon::parse($peakDate, 'UTC')->format('D j M')
                : null,
            'peak_count' => $peakCount,
            'lanes_used' => $lanesUsed,
        ];
    }

    /**
     * The filtered jobs decorated with positioning metadata, lane-packed.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        $windowStart = $this->windowStart();
        $windowEnd = $this->windowEnd();
        $windowSeconds = max(1, $windowStart->diffInSeconds($windowEnd));

        $pct = function (?CarbonInterface $at) use ($windowStart, $windowSeconds): float {
            if ($at === null) {
                return 0.0;
            }

            $offset = $windowStart->diffInSeconds($at, false);

            return max(0.0, min(100.0, ($offset / $windowSeconds) * 100));
        };

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($this->jobs as $job) {
            $start = $job->starts_at;
            $end = $job->ends_at;

            $left = $pct($start);
            $right = $pct($end);
            $width = max(1.5, $right - $left);

            $status = $job->statusEnum();
            $state = $job->state;

            $bands = $this->bandsFor($job, $left, $width, $pct);

            $rows[] = [
                'id' => $job->id,
                'subject' => $job->subject,
                'number' => $job->number,
                'member' => $job->member?->name,
                'venue' => $job->venue?->name ?? $job->store?->name,
                'left' => $left,
                'width' => $width,
                'bands' => $bands,
                'colour' => $this->colourForState($state),
                'status_label' => $status->label(),
                'state_label' => $state->label(),
                'customer_collecting' => (bool) $job->customer_collecting,
                'customer_returning' => (bool) $job->customer_returning,
                'starts_at' => $start,
                'ends_at' => $end,
                'date_range' => $start->format('j M').' – '.$end->format('j M'),
                'extends_before' => $start->lt($windowStart),
                'extends_after' => $end->gt($windowEnd),
                'start_min' => (int) round($windowStart->diffInSeconds($start, false) / 60),
                'end_min' => (int) round($windowStart->diffInSeconds($end, false) / 60),
            ];
        }

        return app(EventLaneAllocator::class)->allocate($rows);
    }

    /**
     * Derive the Delivery / In-Use / Collection sub-bands for a job.
     *
     * @param  callable(?CarbonInterface): float  $pct
     * @return list<array{key: string, label: string, left: float, width: float}>
     */
    private function bandsFor(Opportunity $job, float $barLeft, float $barWidth, callable $pct): array
    {
        $bands = [];

        $relative = function (?CarbonInterface $from, ?CarbonInterface $to) use ($barLeft, $barWidth, $pct): ?array {
            if ($from === null || $to === null || $barWidth <= 0) {
                return null;
            }

            $left = (($pct($from) - $barLeft) / $barWidth) * 100;
            $right = (($pct($to) - $barLeft) / $barWidth) * 100;

            return [
                'left' => max(0.0, min(100.0, $left)),
                'width' => max(0.0, min(100.0, $right) - max(0.0, $left)),
            ];
        };

        $delivery = $relative($job->deliver_starts_at, $job->deliver_ends_at);
        if ($delivery !== null && $delivery['width'] > 0) {
            $bands[] = ['key' => 'delivery', 'label' => __('Delivery')] + $delivery;
        }

        $inUse = $relative($job->show_starts_at, $job->show_ends_at)
            ?? $relative($job->starts_at, $job->ends_at);
        if ($inUse !== null && $inUse['width'] > 0) {
            $bands[] = ['key' => 'in-use', 'label' => __('In Use')] + $inUse;
        }

        $collection = $relative($job->collect_starts_at, $job->collect_ends_at);
        if ($collection !== null && $collection['width'] > 0) {
            $bands[] = ['key' => 'collection', 'label' => __('Collection')] + $collection;
        }

        return $bands;
    }

    /** Map document state to the bar colour (Order / Quote). */
    private function colourForState(OpportunityState $state): string
    {
        return match ($state) {
            OpportunityState::Quotation => 'var(--amber, #f39c12)',
            OpportunityState::Order => '#c0392b',
            default => 'var(--text-muted, #94a3b8)',
        };
    }

    /**
     * Static legend entries for the overlap timeline.
     *
     * @return list<array{label: string, colour: string, icon: ?string}>
     */
    #[Computed]
    public function legend(): array
    {
        return [
            ['label' => __('Order'), 'colour' => '#c0392b', 'icon' => null],
            ['label' => __('Quote'), 'colour' => 'var(--amber, #f39c12)', 'icon' => null],
            ['label' => __('Collecting'), 'colour' => '#27ae60', 'icon' => 'truck'],
            ['label' => __('Returning'), 'colour' => '#17a2b8', 'icon' => 'truck'],
            ['label' => __('Delivery'), 'colour' => '#3498db', 'icon' => null],
            ['label' => __('In Use'), 'colour' => '#f39c12', 'icon' => null],
            ['label' => __('Collection'), 'colour' => '#27ae60', 'icon' => null],
            ['label' => __('Today'), 'colour' => '#ffc107', 'icon' => null, 'border' => true],
            ['label' => __('Extends beyond view'), 'colour' => 'var(--violet, #8b5cf6)', 'icon' => null, 'narrow' => true],
        ];
    }
}; ?>

@php
    $formatter = app(Formatter::class);
    $summary = $this->summary;
@endphp

<div class="s-page">
    <x-signals.page-header title="{{ __('Job Planner') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-5" /></x-slot:icon>
        <x-slot:meta>{{ __('Schedule of quotes & orders across the planning window') }}</x-slot:meta>
    </x-signals.page-header>

    {{-- KPI summary cards --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" wire:loading.class="opacity-60">
        <div class="s-panel">
            <div class="s-panel-body py-3">
                <div class="text-[11px] text-[var(--text-muted)]">{{ __('Total Jobs') }}</div>
                <div class="text-xl font-semibold text-[var(--text-primary)]">{{ $summary['jobs'] }}</div>
            </div>
        </div>
        <div class="s-panel">
            <div class="s-panel-body py-3">
                <div class="text-[11px] text-[var(--text-muted)]">{{ __('Total Value') }}</div>
                <div class="text-xl font-semibold text-[var(--text-primary)]">
                    {{ $formatter->money($summary['total_value_minor'], $summary['total_value_currency']) }}
                </div>
            </div>
        </div>
        <div class="s-panel">
            <div class="s-panel-body py-3">
                <div class="text-[11px] text-[var(--text-muted)]">{{ __('Orders / Quotes') }}</div>
                <div class="text-xl font-semibold">
                    <span class="text-[#c0392b]">{{ $summary['orders'] }}</span>
                    <span class="text-[var(--text-muted)]">/</span>
                    <span class="text-[var(--amber,#f39c12)]">{{ $summary['quotes'] }}</span>
                </div>
            </div>
        </div>
        <div class="s-panel">
            <div class="s-panel-body py-3">
                <div class="text-[11px] text-[var(--text-muted)]">{{ __('Peak Day') }}</div>
                <div class="text-base font-semibold text-[var(--text-primary)]">
                    @if($summary['peak_day_label'])
                        {{ $summary['peak_day_label'] }}
                        <span class="s-badge s-badge-red ml-1">{{ $summary['peak_count'] }}</span>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
        <div class="s-panel">
            <div class="s-panel-body py-3">
                <div class="text-[11px] text-[var(--text-muted)]">{{ __('Lanes Used') }}</div>
                <div class="text-xl font-semibold text-[var(--text-primary)]">{{ $summary['lanes_used'] }}</div>
            </div>
        </div>
    </div>

    {{-- Control bar --}}
    <div class="s-panel mb-4">
        <div class="s-panel-body flex flex-wrap items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="s-field-label" for="planner-date">{{ __('Date') }}</label>
                <input id="planner-date" type="date" wire:model.live="date" class="s-input" />
            </div>

            <div class="flex items-end gap-1">
                <button type="button" wire:click="previousPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Previous period') }}">
                    <flux:icon.chevron-left class="!size-4" />
                </button>
                <button type="button" wire:click="today" class="s-btn s-btn-sm s-btn-ghost">{{ __('Today') }}</button>
                <button type="button" wire:click="nextPeriod" class="s-btn s-btn-sm s-btn-ghost" aria-label="{{ __('Next period') }}">
                    <flux:icon.chevron-right class="!size-4" />
                </button>
            </div>

            <div class="flex flex-col gap-1">
                <span class="s-field-label">{{ __('Zoom') }}</span>
                <div class="inline-flex rounded-sm border border-[var(--card-border)] p-0.5">
                    @foreach(['1w' => __('1W'), '2w' => __('2W'), '1m' => __('1M')] as $zoom => $zoomLabel)
                        <button type="button" wire:click="setZoom('{{ $zoom }}')"
                                class="s-btn s-btn-sm {{ $viewPeriod === $zoom && $preset === '' ? 's-btn-primary' : 's-btn-ghost' }}"
                                wire:key="zoom-{{ $zoom }}">{{ $zoomLabel }}</button>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <span class="s-field-label">{{ __('Presets') }}</span>
                <div class="inline-flex flex-wrap gap-1">
                    <button type="button" wire:click="applyPreset('next_30_days')"
                            class="s-btn s-btn-sm {{ $preset === 'next_30_days' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Next 30d') }}</button>
                    <button type="button" wire:click="applyPreset('this_month')"
                            class="s-btn s-btn-sm {{ $preset === 'this_month' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('This Month') }}</button>
                    <button type="button" wire:click="applyPreset('next_month')"
                            class="s-btn s-btn-sm {{ $preset === 'next_month' ? 's-btn-primary' : 's-btn-ghost' }}">{{ __('Next Month') }}</button>
                </div>
            </div>

            <div class="flex flex-col gap-1">
                <label class="s-field-label" for="planner-store">{{ __('Store') }}</label>
                <select id="planner-store" wire:model.live="storeId" class="s-select">
                    <option value="0">{{ __('All stores') }}</option>
                    @foreach($this->stores as $store)
                        <option value="{{ $store->id }}" wire:key="store-{{ $store->id }}">{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <span class="s-field-label">{{ __('Include') }}</span>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-1.5 text-[12px]">
                        <input type="checkbox" wire:model.live="includeOrders" class="s-checkbox" />
                        <span class="s-badge text-white" style="background: #c0392b;">{{ __('Orders') }}</span>
                    </label>
                    <label class="flex items-center gap-1.5 text-[12px]">
                        <input type="checkbox" wire:model.live="includeQuotations" class="s-checkbox" />
                        <span class="s-badge text-white" style="background: var(--amber, #f39c12);">{{ __('Quotes') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-1 flex-col gap-1" style="min-width: 180px;">
                <label class="s-field-label" for="planner-search">{{ __('Search') }}</label>
                <input id="planner-search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ __('Subject, number or member') }}" class="s-input" />
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-[var(--text-muted)]">
        <span class="font-semibold uppercase tracking-wide">{{ __('Key') }}</span>
        @foreach($this->legend as $entry)
            <span class="flex items-center gap-1.5" wire:key="legend-{{ $loop->index }}">
                @if(isset($entry['narrow']))
                    <span class="inline-block h-2.5 w-1 rounded-sm" style="background: {{ $entry['colour'] }};"></span>
                @elseif(isset($entry['border']))
                    <span class="inline-block h-3 w-4 rounded-sm border border-[#ffc107] bg-[#fff3cd]"></span>
                @else
                    <span class="inline-block h-3 w-4 rounded-sm" style="background: {{ $entry['colour'] }};"></span>
                @endif
                @if(isset($entry['icon']))
                    <flux:icon.truck class="!size-3" />
                @endif
                {{ $entry['label'] }}
            </span>
        @endforeach
    </div>

    @if($this->jobsTruncated)
        <p class="mb-2 text-[11px] text-[var(--text-muted)]">{{ __('Showing first :count jobs — refine filters to see more.', ['count' => 200]) }}</p>
    @endif

    @include('livewire.planner.partials.grid')
</div>

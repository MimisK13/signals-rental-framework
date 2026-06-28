<?php

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Calendar\EventLaneAllocator;
use App\Models\Member;
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
            ->with([
                'member:id,name',
                'member.addresses' => fn ($q) => $q->orderByDesc('is_primary'),
                'venue:id,name',
                'venue.addresses' => fn ($q) => $q->orderByDesc('is_primary'),
                'store:id,name',
            ])
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

    /** Maximum per-day job count in the window (for heatmap intensity scaling). */
    #[Computed]
    public function jobCountHeatmapMax(): int
    {
        return max(1, max($this->jobCountsPerDay));
    }

    /**
     * The filtered jobs decorated with positioning metadata, lane-packed.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        $formatter = app(Formatter::class);
        $windowStart = $this->windowStart();
        $windowEnd = $this->windowEnd();
        $days = $this->dayColumns();
        $dayCount = max(1, count($days));

        /** @var list<array<string, mixed>> $rows */
        $rows = [];

        foreach ($this->jobs as $job) {
            $start = $job->starts_at;
            $end = $job->ends_at;

            $position = $this->barPositionFor($job, $days, $dayCount);

            $status = $job->statusEnum();
            $state = $job->state;

            $rows[] = [
                'id' => $job->id,
                'subject' => $job->subject,
                'number' => $job->number,
                'member' => $job->member?->name,
                'location_label' => $job->venue?->name ?? $job->member?->name ?? $job->store?->name,
                'address' => $this->locationAddressFor($job),
                'left' => $position['left'],
                'width' => $position['width'],
                'day_segments' => $this->daySegmentsFor($job),
                'colour' => $this->colourForState($state),
                'status_label' => $status->label(),
                'state_label' => $state->label(),
                'customer_collecting' => (bool) $job->customer_collecting,
                'customer_returning' => (bool) $job->customer_returning,
                'starts_at' => $start,
                'ends_at' => $end,
                'datetime_range' => $formatter->dateTime($start).' – '.$formatter->dateTime($end),
                'extends_before' => $start->lt($windowStart),
                'extends_after' => $end->gt($windowEnd),
                'start_min' => (int) round($windowStart->diffInSeconds($start, false) / 60),
                'end_min' => (int) round($windowStart->diffInSeconds($end, false) / 60),
                'gap_left_px' => 0,
                'gap_right_px' => 0,
            ];
        }

        $rows = app(EventLaneAllocator::class)->allocate($rows);

        return $this->applyAdjacentBarGaps($rows);
    }

    /**
     * Add a small visual gap between consecutive jobs in the same lane that touch
     * at a day boundary (so adjacent cards do not visually merge).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyAdjacentBarGaps(array $rows): array
    {
        $grouped = collect($rows)->groupBy('lane');

        foreach ($grouped as $laneRows) {
            $sorted = $laneRows->sortBy('start_min')->values()->all();

            for ($i = 1; $i < count($sorted); $i++) {
                $previous = $sorted[$i - 1];
                $current = $sorted[$i];

                if ($previous['end_min'] > $current['start_min']) {
                    continue;
                }

                foreach ($rows as &$row) {
                    if ($row['id'] === $previous['id']) {
                        $row['gap_right_px'] = 2;
                    }
                    if ($row['id'] === $current['id']) {
                        $row['gap_left_px'] = 2;
                    }
                }
                unset($row);
            }
        }

        return $rows;
    }

    /**
     * Snap bar left/width to whole day columns in the window grid.
     *
     * @param  list<Carbon>  $days
     * @return array{left: float, width: float}
     */
    private function barPositionFor(Opportunity $job, array $days, int $dayCount): array
    {
        $windowStartDay = $this->windowStart()->copy()->startOfDay();
        $windowEndDay = $this->windowEnd()->copy()->startOfDay();
        $jobStartDay = $job->starts_at->copy()->startOfDay();
        $jobEndDay = $job->ends_at->copy()->startOfDay();

        $visibleStart = $jobStartDay->greaterThan($windowStartDay) ? $jobStartDay : $windowStartDay;
        $visibleEnd = $jobEndDay->lessThan($windowEndDay) ? $jobEndDay : $windowEndDay;

        $startIndex = 0;
        $endIndex = $dayCount - 1;

        foreach ($days as $index => $day) {
            $dayStart = $day->copy()->startOfDay();
            if ($dayStart->equalTo($visibleStart)) {
                $startIndex = $index;
            }
            if ($dayStart->equalTo($visibleEnd)) {
                $endIndex = $index;
            }
        }

        $span = max(1, $endIndex - $startIndex + 1);

        return [
            'left' => ($startIndex / $dayCount) * 100,
            'width' => ($span / $dayCount) * 100,
        ];
    }

    /**
     * Per-day phase segments snapped to window day columns (delivery / in-use / collection).
     *
     * @return list<array{day_index: int, phase: string, label: string}>
     */
    private function daySegmentsFor(Opportunity $job): array
    {
        $segments = [];

        foreach ($this->dayColumns() as $index => $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            if ($job->starts_at > $dayEnd || $job->ends_at < $dayStart) {
                continue;
            }

            $phase = $this->phaseForDay($job, $dayStart, $dayEnd);

            $segments[] = [
                'day_index' => $index,
                'phase' => $phase,
                'label' => match ($phase) {
                    'delivery' => __('Delivery'),
                    'collection' => __('Collection'),
                    default => __('In Use'),
                },
            ];
        }

        return $segments;
    }

    private function phaseForDay(Opportunity $job, Carbon $dayStart, Carbon $dayEnd): string
    {
        $overlaps = function (?CarbonInterface $from, ?CarbonInterface $to) use ($dayStart, $dayEnd): bool {
            if ($from === null || $to === null) {
                return false;
            }

            return $from <= $dayEnd && $to >= $dayStart;
        };

        if ($overlaps($job->deliver_starts_at, $job->deliver_ends_at)) {
            return 'delivery';
        }

        if ($overlaps($job->collect_starts_at, $job->collect_ends_at)) {
            return 'collection';
        }

        if ($overlaps($job->show_starts_at, $job->show_ends_at)) {
            return 'in-use';
        }

        if ($job->show_starts_at !== null && $dayEnd < $job->show_starts_at
            && $overlaps($job->starts_at, $job->show_starts_at)) {
            return 'delivery';
        }

        if ($job->show_ends_at !== null && $dayStart > $job->show_ends_at
            && $overlaps($job->show_ends_at, $job->ends_at)) {
            return 'collection';
        }

        return 'in-use';
    }

    /** Venue (or member) address line, falling back to the location name. */
    private function locationAddressFor(Opportunity $job): string
    {
        $entity = $job->venue ?? $job->member;
        $fallback = $job->venue?->name ?? $job->member?->name ?? $job->store?->name ?? '';

        if ($entity instanceof Member) {
            $address = $entity->addresses->firstWhere('is_primary', true)
                ?? $entity->addresses->first();

            if ($address !== null) {
                $line = collect([$address->street, $address->city, $address->postcode])
                    ->filter(fn (?string $part): bool => $part !== null && $part !== '')
                    ->implode(', ');

                if ($line !== '') {
                    return $line;
                }
            }
        }

        return $fallback;
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

    /** Phase band colours — shared by the legend and card status row. */
    public function phaseColours(): array
    {
        return [
            'delivery' => '#3498db',
            'in-use' => '#f39c12',
            'collection' => '#27ae60',
        ];
    }

    /** Customer Collecting / Returning badge colours — match the legend key. */
    public function customerBadgeColours(): array
    {
        return [
            'collecting' => '#27ae60',
            'returning' => '#17a2b8',
        ];
    }

    /**
     * Static legend entries for the overlap timeline.
     *
     * @return list<array{label: string, colour: string, icon: ?string}>
     */
    #[Computed]
    public function legend(): array
    {
        $colours = $this->phaseColours();

        return [
            ['label' => __('Order'), 'colour' => '#c0392b', 'icon' => null],
            ['label' => __('Quote'), 'colour' => 'var(--amber, #f39c12)', 'icon' => null],
            ['label' => __('Collecting'), 'colour' => '#27ae60', 'icon' => 'truck'],
            ['label' => __('Returning'), 'colour' => '#17a2b8', 'icon' => 'truck'],
            ['label' => __('Delivery'), 'colour' => $colours['delivery'], 'icon' => null],
            ['label' => __('In Use'), 'colour' => $colours['in-use'], 'icon' => null],
            ['label' => __('Collection'), 'colour' => $colours['collection'], 'icon' => null],
            ['label' => __('Today'), 'colour' => '#ffc107', 'icon' => null, 'border' => true],
            ['label' => __('Extends beyond view'), 'colour' => 'var(--violet, #8b5cf6)', 'icon' => null, 'narrow' => true],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="{{ __('Job Planner') }}">
        <x-slot:icon><flux:icon.calendar-days class="!size-5" /></x-slot:icon>
        <x-slot:meta>{{ __('Schedule of quotes & orders across the planning window') }}</x-slot:meta>
    </x-signals.page-header>

    <div class="flex-1 px-6 py-4 max-md:px-5 max-sm:px-3">
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
</section>

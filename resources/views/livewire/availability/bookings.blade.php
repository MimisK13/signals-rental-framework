<?php

use App\Data\Availability\GanttDemandBarData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\AvailabilityService;
use App\Services\Planning\PlanningStoreFilter;
use App\Services\Opportunities\OpportunityPresentation;
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
 * Per-product availability / bookings grid — Current RMS "Product Bookings".
 *
 * One product at one store over a date window: summary rows (available, held,
 * post-rent), then opportunity demand rows, then serialised asset rows.
 */
new #[Layout('components.layouts.app')] #[Title('Product Bookings')] class extends Component
{
    /** The selectable period lengths (in days). */
    private const PERIOD_OPTIONS = [7, 14, 28, 30, 60];

    /** Matches the group availability grid warning threshold. */
    private const WARNING_THRESHOLD = 2;

    public Product $product;

    public string $from;

    public int $days;

    #[Url(as: 'stores')]
    public array $storeIds = [];

    /** Whether the grid body has completed its deferred first paint. */
    public bool $gridReady = false;

    public function mount(Product $product, string $from, int $days): void
    {
        Gate::authorize('availability.view');

        $this->product = $product;
        $this->from = $from;
        $this->days = in_array($days, self::PERIOD_OPTIONS, true) ? $days : 30;
    }

    /** Flip the grid-ready flag after the initial skeleton paint (wire:init). */
    public function initializeGrid(): void
    {
        $this->gridReady = true;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'days'], true)) {
            $this->redirectToBookings();
        }
    }

    public function updatedStoreIds(): void
    {
        $this->redirectToBookings();
    }

    public function syncStoreIdsFromTags(array $tagNames): void
    {
        $this->storeIds = app(PlanningStoreFilter::class)->storeIdsFromTags($tagNames);
    }

    public function selectedStoreTags(): array
    {
        return app(PlanningStoreFilter::class)->selectedStoreTags($this->storeIds);
    }

    public function storeNameSuggestions(): array
    {
        return app(PlanningStoreFilter::class)->storeNameSuggestions();
    }

    /**
     * @return list<int>
     */
    public function resolvedStoreIds(): array
    {
        return app(PlanningStoreFilter::class)->resolvedStoreIds($this->storeIds);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Product Bookings'));
    }

    /**
     * @return list<int>
     */
    public function periodOptions(): array
    {
        return self::PERIOD_OPTIONS;
    }

    public function redirectToBookings(): void
    {
        $this->redirectRoute('availability.bookings', array_filter([
            'product' => $this->product->id,
            'from' => $this->fromDate(),
            'days' => $this->days,
            'stores' => $this->storeIds !== [] ? $this->storeIds : null,
        ], static fn (mixed $value): bool => $value !== null), navigate: true);
    }

    public function previousPeriod(): void
    {
        $this->from = Carbon::parse($this->fromDate())->subDays($this->days)->toDateString();
        $this->redirectToBookings();
    }

    public function nextPeriod(): void
    {
        $this->from = Carbon::parse($this->fromDate())->addDays($this->days)->toDateString();
        $this->redirectToBookings();
    }

    public function windowTo(): string
    {
        return Carbon::parse($this->fromDate())->addDays(max(1, $this->days) - 1)->toDateString();
    }

    private function fromDate(): string
    {
        return $this->from !== '' ? $this->from : Carbon::today('UTC')->toDateString();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function windowDays(): array
    {
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->windowTo());

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    #[Computed]
    public function gantt(): ?\App\Data\Availability\GanttData
    {
        if (! $this->gridReady || $this->resolvedStoreIds() === []) {
            return null;
        }

        return app(AvailabilityService::class)->getGanttForStores(
            $this->product->id,
            $this->storeIds,
            Carbon::parse($this->fromDate()),
            Carbon::parse($this->windowTo()),
        );
    }

    /**
     * @return array{available: list<array<string, mixed>>, held: list<array{date: string, value: int}>, post_rent: list<array{date: string, value: int}>, total_stock: int}
     */
    #[Computed]
    public function summaryRows(): array
    {
        $empty = ['available' => [], 'held' => [], 'post_rent' => [], 'total_stock' => 0];

        if (! $this->gridReady || $this->resolvedStoreIds() === []) {
            return $empty;
        }

        $service = app(AvailabilityService::class);
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->windowTo());

        $calendar = $service->getCalendarForStores($this->storeIds, $from, $to, [$this->product->id]);
        $productRow = $calendar->first();
        $stockByProduct = $service->productTotalStockForStores([$this->product->id], $this->storeIds);
        $totalStock = $stockByProduct[$this->product->id] ?? 0;

        $daysByDate = $productRow !== null
            ? collect($productRow->days)->keyBy('date')
            : collect();

        $available = [];
        $held = [];
        $postRent = [];

        foreach ($this->windowDays as $day) {
            $dayData = $daysByDate->get($day);

            if ($dayData === null) {
                $available[] = [
                    'date' => $day,
                    'present' => false,
                ];
                $held[] = ['date' => $day, 'value' => ''];
                $postRent[] = ['date' => $day, 'value' => ''];

                continue;
            }

            $availableQty = $dayData->available;
            $heldQty = max(0, $totalStock - $availableQty);
            $state = $this->classifyCell($availableQty, $dayData->has_shortage);

            $available[] = [
                'date' => $day,
                'present' => true,
                'available' => $availableQty,
                'held' => $heldQty,
                'stock' => $totalStock,
                'used_pct' => $totalStock > 0
                    ? min(100, max(0, (int) round(($heldQty / $totalStock) * 100)))
                    : ($heldQty > 0 ? 100 : 0),
                'state' => $state,
            ];
            $held[] = ['date' => $day, 'value' => $heldQty];
            $postRent[] = ['date' => $day, 'value' => $dayData->pending_checkin];
        }

        return [
            'available' => $available,
            'held' => $held,
            'post_rent' => $postRent,
            'total_stock' => $totalStock,
        ];
    }

    public function classifyCell(int $available, bool $hasShortage): string
    {
        if ($hasShortage || $available < 0) {
            return 'shortage';
        }

        if ($available === 0) {
            return 'booked';
        }

        if ($available <= self::WARNING_THRESHOLD) {
            return 'warning';
        }

        return 'available';
    }

    /**
     * @return list<array{opportunity_id: int, label: string, url: string, cells: list<array{date: string, quantity: int|null, tone: string|null, colour: string|null}>}>
     */
    #[Computed]
    public function opportunityRows(): array
    {
        if (! $this->gridReady) {
            return [];
        }

        $gantt = $this->gantt;

        if ($gantt === null) {
            return [];
        }

        /** @var Collection<int, GanttDemandBarData> $bulkBars */
        $bulkBars = collect($gantt->demands)->filter(static fn (GanttDemandBarData $bar): bool => $bar->asset_id === null);

        if ($bulkBars->isEmpty()) {
            return [];
        }

        $itemsById = $this->opportunityItemsForGantt();

        /** @var Collection<string, Collection<int, GanttDemandBarData>> $grouped */
        $grouped = $bulkBars->groupBy(fn (GanttDemandBarData $bar): string => (string) ($this->opportunityIdForBar($bar, $itemsById) ?? 'source-'.$bar->source_type.'-'.$bar->source_id));

        $rows = [];

        foreach ($grouped as $bars) {
            $first = $bars->first();
            $opportunity = $this->opportunityForBar($first, $itemsById);
            $opportunityId = $opportunity?->id;

            $label = $opportunity !== null
                ? $this->formatOpportunityLabel($opportunity)
                : ($first->source_name ?? __('Booking #:id', ['id' => $first->source_id]));

            $url = $opportunity !== null ? route('opportunities.show', $opportunity) : '#';

            $cells = [];
            foreach ($this->windowDays as $day) {
                $cells[] = $this->cellForDemandBars($bars->all(), $day, $itemsById);
            }

            $rows[] = [
                'opportunity_id' => $opportunityId ?? 0,
                'label' => $label,
                'url' => $url,
                'cells' => $cells,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $rows;
    }

    /**
     * @return list<array{asset_id: int, label: string, url: string|null, cells: list<array{date: string, quantity: int|null, tone: string|null, colour: string|null}>}>
     */
    #[Computed]
    public function assetRows(): array
    {
        if (! $this->gridReady || $this->resolvedStoreIds() === []) {
            return [];
        }

        $gantt = $this->gantt;
        $barsByAsset = $gantt === null
            ? collect()
            : collect($gantt->demands)
                ->filter(static fn (GanttDemandBarData $bar): bool => $bar->asset_id !== null)
                ->groupBy(static fn (GanttDemandBarData $bar): int => (int) $bar->asset_id);

        $itemsById = $this->opportunityItemsForGantt();

        $assets = StockLevel::query()
            ->where('product_id', $this->product->id)
            ->whereIn('store_id', $this->resolvedStoreIds())
            ->whereNotNull('asset_number')
            ->orderBy('asset_number')
            ->get(['id', 'asset_number', 'serial_number']);

        $rows = [];

        foreach ($assets as $asset) {
            /** @var Collection<int, GanttDemandBarData> $bars */
            $bars = $barsByAsset->get($asset->id, collect());
            $label = $asset->asset_number ?? $asset->serial_number ?? __('Asset #:id', ['id' => $asset->id]);

            $cells = [];
            foreach ($this->windowDays as $day) {
                $cells[] = $this->cellForDemandBars($bars->all(), $day, $itemsById);
            }

            $rows[] = [
                'asset_id' => $asset->id,
                'label' => $label,
                'url' => null,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, \App\Models\OpportunityItem>
     */
    private function opportunityItemsForGantt(): Collection
    {
        $gantt = $this->gantt;

        if ($gantt === null) {
            return collect();
        }

        $itemIds = collect($gantt->demands)
            ->filter(static fn (GanttDemandBarData $bar): bool => $bar->source_type === 'opportunity_item')
            ->map(static fn (GanttDemandBarData $bar): int => $bar->source_id)
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return collect();
        }

        return \App\Models\OpportunityItem::query()
            ->whereKey($itemIds)
            ->with('opportunity')
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<GanttDemandBarData>  $bars
     * @return array{date: string, quantity: int|null, tone: string|null, colour: string|null}
     */
    private function cellForDemandBars(array $bars, string $day, Collection $itemsById): array
    {
        $quantity = 0;
        $tone = null;
        $colour = null;
        $presentation = app(OpportunityPresentation::class);
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = Carbon::parse($day)->endOfDay();

        foreach ($bars as $bar) {
            $opportunity = $this->opportunityForBar($bar, $itemsById);
            $phaseColour = $opportunity !== null
                ? $presentation->phaseColour($presentation->phaseForDay($opportunity, $dayStart, $dayEnd))
                : null;

            if ($this->isoOverlapsDay($bar->starts_at, $bar->ends_at, $day)) {
                $quantity += $bar->quantity;
                $tone = 'hire';
                $colour = $phaseColour ?? $presentation->phaseColour('in-use');
            } elseif ($this->isoOverlapsDay($bar->period_start, $bar->buffer_before_end, $day)
                || $this->isoOverlapsDay($bar->buffer_after_start, $bar->period_end, $day)) {
                $tone = $tone ?? 'buffer';
                $colour = $colour ?? ($phaseColour ?? $presentation->phaseColour('in-use'));
            }
        }

        return [
            'date' => $day,
            'quantity' => $quantity > 0 ? $quantity : null,
            'tone' => $tone,
            'colour' => $colour,
        ];
    }

    /**
     * Opportunity phase legend entries for the bookings grid key.
     *
     * @return list<array{label: string, colour: string, icon: null}>
     */
    #[Computed]
    public function opportunityPhaseLegend(): array
    {
        return app(OpportunityPresentation::class)->phaseLegendEntries();
    }

    /**
     * @param  Collection<int, \App\Models\OpportunityItem>  $itemsById
     */
    private function opportunityForBar(GanttDemandBarData $bar, Collection $itemsById): ?Opportunity
    {
        if ($bar->source_type !== 'opportunity_item') {
            return null;
        }

        return $itemsById->get($bar->source_id)?->opportunity;
    }

    private function isoOverlapsDay(string $isoStart, string $isoEnd, string $day): bool
    {
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = Carbon::parse($day)->endOfDay();
        $start = Carbon::parse($isoStart);
        $end = Carbon::parse($isoEnd);

        return $start->lessThanOrEqualTo($dayEnd) && $end->greaterThanOrEqualTo($dayStart);
    }

    /**
     * @param  Collection<int, \App\Models\OpportunityItem>  $itemsById
     */
    private function opportunityIdForBar(GanttDemandBarData $bar, Collection $itemsById): ?int
    {
        return $this->opportunityForBar($bar, $itemsById)?->id;
    }

    private function formatOpportunityLabel(Opportunity $opportunity): string
    {
        $datePrefix = $opportunity->starts_at !== null
            ? $opportunity->starts_at->format('d/m').' - '
            : '';

        $number = $opportunity->number !== null && $opportunity->number !== ''
            ? ' ('.$opportunity->number.')'
            : '';

        return $datePrefix.($opportunity->subject ?? __('Opportunity')). $number;
    }
}; ?>

@php
    $dayList = $this->windowDays;
    $availabilityIndexUrl = route('availability.index', array_filter([
        'stores' => $storeIds !== [] ? $storeIds : null,
        'from' => $from !== '' ? $from : null,
        'days' => $days,
        'group' => $product->product_group_id,
    ]));
@endphp

<section class="w-full s-avail-grid-page s-avail-grid-page--bookings">
    <div class="s-avail-grid-sticky-chrome sticky top-0 bg-[var(--content-bg)] shadow-sm">
        <x-signals.page-header title="{{ __('Product Bookings') }}">
            <x-slot:meta>
                <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">{{ __('Job Planning') }}</span>
            </x-slot:meta>
            <x-slot:actions>
                <flux:button variant="ghost" href="{{ $availabilityIndexUrl }}" wire:navigate.hover icon="arrow-left">
                    {{ __('Equipment Availability') }}
                </flux:button>
            </x-slot:actions>
        </x-signals.page-header>

        <div class="px-6 pb-4 max-md:px-5 max-sm:px-3">
            <div class="s-panel s-planning-filter-panel">
                <div class="s-panel-body flex flex-wrap items-end gap-3">
                    @include('livewire.partials.filter-store-tags')

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Product') }}</span>
                        <div class="s-select-display min-w-52" title="{{ $product->name }}">
                            <span class="s-select-display-text">{{ $product->name }}</span>
                            <a href="{{ route('products.show', $product) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="s-select-display-action"
                               title="{{ __('Product details') }}">
                                <flux:icon.information-circle class="!size-3.5" />
                            </a>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Start date') }}</span>
                        <x-signals.datepicker
                            wire:key="bookings-from-{{ $from }}"
                            :value="$from !== '' ? $from : null"
                            placeholder="{{ __('Pick a date') }}"
                            floating
                            class="s-avail-filter-datepicker min-w-[9.5rem]"
                            x-on:input="$wire.set('from', $event.detail)"
                        />
                    </div>

                    <div class="flex flex-col gap-1">
                        <span class="s-field-label">{{ __('Days') }}</span>
                        <select wire:model.live="days" class="s-select min-w-[9.5rem]" aria-label="{{ __('Days period') }}">
                            @foreach($this->periodOptions() as $option)
                                <option value="{{ $option }}">{{ $option }} {{ __('days') }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($this->resolvedStoreIds() === [])
        <div class="px-6 max-md:px-5 max-sm:px-3">
            <x-signals.empty
                title="{{ __('No stores available') }}"
                description="{{ __('Add a store to view bookings.') }}">
                <x-slot:icon><flux:icon.building-storefront class="!size-7" /></x-slot:icon>
            </x-signals.empty>
        </div>
    @else
        <div class="px-6 pt-4 pb-4 max-md:px-5 max-sm:px-3">
            @include('livewire.partials.planning-grid-shell', [
                'contentView' => 'livewire.availability.partials.bookings-content',
                'skeletonView' => 'livewire.availability.partials.grid-skeleton',
            ])
        </div>
    @endif
</section>

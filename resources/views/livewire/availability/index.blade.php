<?php

use App\Data\Availability\CalendarProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * Standalone Equipment Availability page — by-product-group grid (M8-4b).
 *
 * Renders a product-group's products (rows) x days (columns) for a chosen store
 * and date window. Calls {@see AvailabilityService} directly (same layer as the
 * API); reads gate on `availability.view`.
 *
 * Reverb-live: subscribes to `availability.store.{storeId}` and re-reads the
 * daily-summary read model on `availability.changed`.
 */
new #[Layout('components.layouts.app')] #[Title('Equipment Availability')] class extends Component
{
    use WithPagination;

    /**
     * The "warning" threshold for the product-group grid: a cell with availability
     * at or below this (but still above zero) is amber/warning, above it is green.
     */
    private const WARNING_THRESHOLD = 2;

    /** The selectable period lengths (in days) for the product-group grid. */
    private const PERIOD_OPTIONS = [7, 14, 28, 30, 60];

    #[Url(as: 'store')]
    public int $storeId = 0;

    #[Url(as: 'from')]
    public string $from = '';

    /** The product group whose products fill the rows. */
    #[Url(as: 'group')]
    public ?int $groupId = null;

    /** Product-type filter: `rental`, `sale` or empty (all). */
    #[Url(as: 'type')]
    public string $productType = '';

    /** Show only rows that have a shortage cell in the window. */
    #[Url(as: 'shortages')]
    public bool $shortagesOnly = false;

    /** Show only rows that have a warning (low-but-positive) cell in the window. */
    #[Url(as: 'warnings')]
    public bool $warningsOnly = false;

    /**
     * Whether quote-stage (provisional) demand counts towards held/available.
     * Wiring depends on the availability engine exposing a demand-phase filter on
     * the read model; see {@see groupRows()}.
     */
    #[Url(as: 'quotes')]
    public bool $includeQuotes = true;

    /** The window length in days for the group grid (default ~30). */
    #[Url(as: 'days')]
    public int $daysPeriod = 30;

    public function mount(): void
    {
        Gate::authorize('availability.view');

        if ($this->storeId === 0) {
            $this->storeId = (int) (Store::query()->where('is_default', true)->value('id')
                ?? Store::query()->orderBy('id')->value('id')
                ?? 0);
        }

        if ($this->from === '') {
            $this->from = Carbon::today('UTC')->toDateString();
        }

        if (! in_array($this->daysPeriod, self::PERIOD_OPTIONS, true)) {
            $this->daysPeriod = 30;
        }
    }

    /**
     * Reset pagination when a filter changes so the page never lands past the
     * (now shorter) result set.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['groupId', 'productType', 'shortagesOnly', 'warningsOnly', 'includeQuotes', 'daysPeriod', 'storeId', 'from'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return list<int>
     */
    public function periodOptions(): array
    {
        return self::PERIOD_OPTIONS;
    }

    public function rendering(View $view): void
    {
        $view->title(__('Equipment Availability'));
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:availability.store.'.$this->storeId.',.availability.changed' => 'onStoreAvailabilityChanged',
        ];
    }

    public function onStoreAvailabilityChanged(): void
    {
        unset($this->groupRows);
    }

    public function previousPeriod(): void
    {
        $this->from = Carbon::parse($this->fromDate())->subDays($this->daysPeriod)->toDateString();
        $this->resetPage();
    }

    public function nextPeriod(): void
    {
        $this->from = Carbon::parse($this->fromDate())->addDays($this->daysPeriod)->toDateString();
        $this->resetPage();
    }

    /**
     * The group grid's window end (Y-m-d): start date + the days-period.
     */
    public function groupTo(): string
    {
        return Carbon::parse($this->fromDate())->addDays(max(1, $this->daysPeriod) - 1)->toDateString();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function groupDays(): array
    {
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->groupTo());

        $maxDays = 62;
        if ($from->diffInDays($to) > $maxDays) {
            $to = $from->copy()->addDays($maxDays);
        }

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    #[Computed]
    public function groupRows(): array
    {
        $empty = ['rows' => [], 'paginator' => Product::query()->whereRaw('1 = 0')->paginate(20)];

        if ($this->storeId === 0 || $this->groupId === null) {
            return $empty;
        }

        $group = ProductGroup::query()->find($this->groupId);

        if ($group === null) {
            return $empty;
        }

        $productsQuery = $group->products()
            ->where('is_kit', false)
            ->whereNull('container_availability_mode')
            ->orderBy('name');

        $type = ProductType::tryFrom($this->productType);
        if ($type !== null) {
            $productsQuery->where('product_type', $type);
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Product> $paginator */
        $paginator = $productsQuery->paginate(20);

        /** @var list<int> $productIds */
        $productIds = $paginator->getCollection()->map(static fn (Product $p): int => (int) $p->id)->all();

        if ($productIds === []) {
            return ['rows' => [], 'paginator' => $paginator];
        }

        $service = app(AvailabilityService::class);
        $from = Carbon::parse($this->fromDate());
        $to = Carbon::parse($this->groupTo());

        $calendar = $service->getCalendar($this->storeId, $from, $to, $productIds)->keyBy('product_id');
        $totalStock = $service->productTotalStock($productIds, $this->storeId);
        $days = $this->groupDays();

        $rows = [];

        foreach ($paginator->getCollection() as $product) {
            $productId = (int) $product->id;
            /** @var CalendarProductData|null $productCalendar */
            $productCalendar = $calendar->get($productId);
            $cellsByDate = $productCalendar !== null
                ? collect($productCalendar->days)->keyBy('date')
                : collect();

            $stock = $totalStock[$productId] ?? 0;
            $cells = [];
            $hasShortage = false;
            $hasWarning = false;

            foreach ($days as $day) {
                $cell = $cellsByDate->get($day);

                if ($cell === null) {
                    $cells[] = ['date' => $day, 'present' => false];

                    continue;
                }

                $available = $cell->available;
                $held = max(0, $stock - $available);
                $state = $this->classifyCell($available, $cell->has_shortage);

                if ($state === 'shortage') {
                    $hasShortage = true;
                }
                if ($state === 'warning') {
                    $hasWarning = true;
                }

                $cells[] = [
                    'date' => $day,
                    'present' => true,
                    'available' => $available,
                    'held' => $held,
                    'stock' => $stock,
                    'used_pct' => $stock > 0
                        ? min(100, max(0, (int) round(($held / $stock) * 100)))
                        : ($held > 0 ? 100 : 0),
                    'state' => $state,
                ];
            }

            if ($this->shortagesOnly && ! $hasShortage) {
                continue;
            }
            if ($this->warningsOnly && ! $hasWarning) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'cells' => $cells,
            ];
        }

        return ['rows' => $rows, 'paginator' => $paginator];
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
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'stores' => Store::query()->inDefaultQueries()->orderBy('name')->get(['id', 'name']),
            'productGroups' => ProductGroup::query()->ordered()->orderBy('name')->get(['id', 'name']),
            'productTypeOptions' => [
                ProductType::Rental->value => ProductType::Rental->label(),
                ProductType::Sale->value => ProductType::Sale->label(),
            ],
            'periodOptions' => $this->periodOptions(),
        ];
    }

    private function fromDate(): string
    {
        return $this->from !== '' ? $this->from : Carbon::today('UTC')->toDateString();
    }
}; ?>

<section class="w-full s-avail-grid-page s-avail-grid-page--index">
    <div class="s-avail-grid-sticky-chrome sticky top-0 z-30 bg-[var(--content-bg)] shadow-sm">
        <x-signals.page-header title="Equipment Availability">
            <x-slot:meta>
                <span style="font-family: var(--font-display); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--blue);">Job Planning</span>
            </x-slot:meta>
        </x-signals.page-header>

        <div class="px-6 pb-4 max-md:px-5 max-sm:px-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Product Group') }}</label>
                    <select wire:model.live="groupId" class="s-select min-w-52" aria-label="{{ __('Product group') }}">
                        <option value="">{{ __('Select a group…') }}</option>
                        @foreach($productGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Store') }}</label>
                    <select wire:model.live="storeId" class="s-select min-w-44" aria-label="{{ __('Store') }}">
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}">{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Product type') }}</label>
                    <select wire:model.live="productType" class="s-select min-w-32" aria-label="{{ __('Product type') }}">
                        <option value="">{{ __('All') }}</option>
                        @foreach($productTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Start date') }}</label>
                    <x-signals.datepicker
                        wire:key="avail-from-{{ $from }}"
                        :value="$from !== '' ? $from : null"
                        placeholder="{{ __('Pick a date') }}"
                        class="s-avail-filter-datepicker min-w-[9.5rem]"
                        x-on:input="$wire.set('from', $event.detail)"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">{{ __('Days') }}</label>
                    <select wire:model.live="daysPeriod" class="s-select min-w-[9.5rem]" aria-label="{{ __('Days period') }}">
                        @foreach($periodOptions as $option)
                            <option value="{{ $option }}">{{ $option }} {{ __('days') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ml-auto flex flex-wrap items-center justify-end gap-4 pb-1.5">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                        <input type="checkbox" wire:model.live="shortagesOnly" class="sr-only" />
                        <x-signals.checkbox :checked="$shortagesOnly" />
                        <span>{{ __('Shortages only') }}</span>
                    </label>
                    <label class="inline-flex cursor-pointer items-center gap-2 text-[12px]">
                        <input type="checkbox" wire:model.live="warningsOnly" class="sr-only" />
                        <x-signals.checkbox :checked="$warningsOnly" />
                        <span>{{ __('Warnings only') }}</span>
                    </label>
                    <label class="inline-flex cursor-not-allowed items-center gap-2 text-[12px] opacity-50"
                           title="{{ __('Coming in a future update — the read model does not yet support a quote-phase filter.') }}">
                        <input type="checkbox" wire:model.live="includeQuotes" class="sr-only" disabled />
                        <x-signals.checkbox :checked="$includeQuotes" />
                        <span>{{ __('Include quotes') }}</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="px-6 pt-4 pb-4 max-md:px-5 max-sm:px-3">
        @include('livewire.availability.partials.group')
    </div>
</section>

<?php

use App\Data\Availability\ProductAvailabilitySummary;
use App\Models\Product;
use App\Models\Store;
use App\Services\Availability\ProductAvailabilitySummaryReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] #[Title('Product Availability')] class extends Component
{
    public Product $product;

    #[Url(as: 'store')]
    public ?int $storeId = null;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public function mount(Product $product): void
    {
        Gate::authorize('availability.view');

        $this->product = $product;
        $this->from = $this->from !== ''
            ? $this->from
            : Carbon::today('UTC')->startOfDay()->format('Y-m-d\\TH:i');
        $this->until = $this->until !== ''
            ? $this->until
            : Carbon::today('UTC')->addDays(7)->startOfDay()->format('Y-m-d\\TH:i');

        $storeIds = $this->stores()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($this->storeId === null || ! in_array($this->storeId, $storeIds, true)) {
            $this->storeId = $storeIds[0] ?? null;
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Availability');
    }

    /**
     * @return Collection<int, Store>
     */
    #[Computed]
    public function stores(): Collection
    {
        return Store::query()
            ->inDefaultQueries()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['storeId', 'from', 'until'], true)) {
            unset($this->summary);
        }
    }

    public function periodError(): ?string
    {
        try {
            $from = Carbon::parse($this->from);
            $until = Carbon::parse($this->until);
        } catch (\Throwable) {
            return 'Enter a valid availability period.';
        }

        return $until->greaterThan($from) ? null : 'The end of the period must be after its start.';
    }

    #[Computed]
    public function summary(): ?ProductAvailabilitySummary
    {
        if ($this->storeId === null || $this->periodError() !== null) {
            return null;
        }

        return app(ProductAvailabilitySummaryReader::class)->read(
            product: $this->product,
            storeId: $this->storeId,
            from: Carbon::parse($this->from),
            until: Carbon::parse($this->until),
        );
    }

    public function formatTimestamp(?string $timestamp): string
    {
        return $timestamp === null ? '—' : Carbon::parse($timestamp)->format('d M Y H:i');
    }

    public function phaseLabel(string $phase): string
    {
        return ucfirst($phase);
    }
}; ?>

<section class="w-full">
    @include('livewire.products.partials.product-header', ['product' => $product, 'subpage' => 'Availability'])
    @include('livewire.products.partials.product-tabs', ['product' => $product, 'activeTab' => 'availability'])

    <div class="space-y-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <div class="s-panel">
            <div class="s-panel-body grid gap-4 md:grid-cols-3 md:items-end">
                <flux:select wire:model.live="storeId" label="Store">
                    @forelse($this->stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @empty
                        <flux:select.option value="">No stores available</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:input wire:model.live.debounce.300ms="from" type="datetime-local" label="From" />
                <flux:input wire:model.live.debounce.300ms="until" type="datetime-local" label="Until" />
            </div>
            @if($this->periodError() !== null)
                <div class="px-4 pb-4 text-sm text-red-700 dark:text-red-300">{{ $this->periodError() }}</div>
            @endif
        </div>

        @if($this->summary === null)
            <x-signals.panel title="Availability">
                <p class="text-sm text-[var(--text-muted)]">Select a valid store and period to view availability.</p>
            </x-signals.panel>
        @else
            @php($summary = $this->summary)

            <x-signals.panel title="Availability Summary">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach([
                        ['label' => 'Physical Stock', 'value' => $summary->physical_stock],
                        ['label' => 'Peak Demand', 'value' => $summary->peak_demand ?? '—'],
                        ['label' => 'Lowest Availability', 'value' => $summary->minimum_availability ?? '—'],
                        ['label' => 'Peak Shortage', 'value' => $summary->peak_shortage ?? '—'],
                        ['label' => 'Shortage', 'value' => $summary->has_shortage === null ? '—' : ($summary->has_shortage ? 'Yes' : 'No')],
                    ] as $stat)
                        <div class="rounded-lg border border-[var(--card-border)] px-4 py-3">
                            <div class="text-2xl font-bold text-[var(--text-primary)]" style="font-family: var(--font-display);">{{ $stat['value'] }}</div>
                            <div class="mt-1 text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]">{{ $stat['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                @if($summary->calculated_at === null)
                    <p class="mt-4 text-sm text-[var(--text-muted)]">No availability snapshot exists for this period yet.</p>
                @endif
            </x-signals.panel>

            <x-signals.panel title="Availability Over Time">
                @if($summary->slots === [])
                    <p class="text-sm text-[var(--text-muted)]">No availability slots are available for this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[38rem] text-left text-sm">
                            <thead class="border-b border-[var(--card-border)] text-xs uppercase tracking-wide text-[var(--text-muted)]">
                                <tr>
                                    <th class="px-3 py-2">Slot</th>
                                    <th class="px-3 py-2">Demand</th>
                                    <th class="px-3 py-2">Available</th>
                                    <th class="px-3 py-2">Shortage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary->slots as $slot)
                                    <tr wire:key="availability-slot-{{ $slot->slot_start }}" class="border-b border-[var(--card-border)] last:border-0">
                                        <td class="px-3 py-2">{{ $this->formatTimestamp($slot->slot_start) }}</td>
                                        <td class="px-3 py-2">{{ $slot->total_demanded }}</td>
                                        <td class="px-3 py-2">{{ $slot->available }}</td>
                                        <td class="px-3 py-2">{{ max(0, -$slot->available) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-signals.panel>

            <x-signals.panel title="Demand Breakdown">
                @if($summary->demand_entries === [])
                    <p class="text-sm text-[var(--text-muted)]">No active demand affects this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[46rem] text-left text-sm">
                            <thead class="border-b border-[var(--card-border)] text-xs uppercase tracking-wide text-[var(--text-muted)]">
                                <tr>
                                    <th class="px-3 py-2">Source</th>
                                    <th class="px-3 py-2">Quantity</th>
                                    <th class="px-3 py-2">From</th>
                                    <th class="px-3 py-2">Until</th>
                                    <th class="px-3 py-2">Phase</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary->demand_entries as $entry)
                                    <tr wire:key="availability-demand-{{ $entry->source_type }}-{{ $entry->source_id }}" class="border-b border-[var(--card-border)] last:border-0">
                                        <td class="px-3 py-2">{{ $entry->label }}</td>
                                        <td class="px-3 py-2">{{ $entry->quantity }}</td>
                                        <td class="px-3 py-2">{{ $this->formatTimestamp($entry->from) }}</td>
                                        <td class="px-3 py-2">{{ $this->formatTimestamp($entry->until) }}</td>
                                        <td class="px-3 py-2">{{ $this->phaseLabel($entry->phase) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-signals.panel>
        @endif
    </div>
</section>

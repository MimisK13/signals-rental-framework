<?php

use App\Actions\Stocktakes\CancelStocktake;
use App\Actions\Stocktakes\CompleteStocktake;
use App\Actions\Stocktakes\RecordBulkStocktakeObservation;
use App\Actions\Stocktakes\RecordSerializedStocktakeObservation;
use App\Actions\Stocktakes\StartStocktake;
use App\Data\Stocktakes\RecordBulkStocktakeObservationData;
use App\Models\StockLevel;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Stocktake $stocktake;

    /** @var array<int, string> */
    public array $counts = [];

    /** @var array<int, string> */
    public array $countNotes = [];

    public string $serializedIdentifier = '';

    public string $serializedNotes = '';

    /** @var list<array{message: string, type: string}> */
    public array $scanFeedback = [];

    public function mount(Stocktake $stocktake): void
    {
        Gate::authorize('stock.count');
        Gate::authorize('access', new StockLevel(['store_id' => $stocktake->store_id]));
        $this->loadStocktake($stocktake);
    }

    public function start(): void
    {
        (new StartStocktake)($this->stocktake);
        $this->loadStocktake($this->stocktake->fresh());
    }

    public function recordCount(int $lineId): void
    {
        $this->validate([
            "counts.{$lineId}" => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            "countNotes.{$lineId}" => ['nullable', 'string', 'max:5000'],
        ], [
            "counts.{$lineId}.regex" => 'Enter a non-negative quantity with up to two decimal places.',
        ]);

        try {
            $line = $this->stocktake->lines()->findOrFail($lineId);
            (new RecordBulkStocktakeObservation)(
                $line,
                RecordBulkStocktakeObservationData::from([
                    'counted_quantity' => (string) $this->counts[$lineId],
                    'notes' => $this->countNotes[$lineId] ?? null,
                ]),
            );
            $this->loadStocktake($this->stocktake->fresh());
            $this->dispatch('stocktake-count-saved');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
        }
    }

    public function complete(): void
    {
        (new CompleteStocktake)($this->stocktake);
        $this->loadStocktake($this->stocktake->fresh());
    }

    public function scanSerialized(): void
    {
        $this->validate([
            'serializedIdentifier' => ['required', 'string', 'max:255'],
            'serializedNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $observation = (new RecordSerializedStocktakeObservation)(
                $this->stocktake,
                $this->serializedIdentifier,
                $this->serializedNotes,
            );

            $this->scanFeedback[] = [
                'message' => $observation->isUnresolved()
                    ? $this->serializedIdentifier.' — Unknown identifier; finding recorded.'
                    : ($observation->wasRecentlyCreated
                        ? (($observation->stocktake_line_id !== null ? '✓ ' : '! ').$this->displayObservation($observation).$this->displayObservationContext($observation))
                        : $this->displayObservation($observation).' — Already observed.'),
                'type' => $observation->isUnresolved()
                    ? 'unresolved'
                    : ($observation->stocktake_line_id !== null ? 'expected' : 'unexpected'),
            ];
            $this->scanFeedback = array_slice($this->scanFeedback, -10);
            $this->serializedIdentifier = '';
            $this->serializedNotes = '';
            $this->loadStocktake($this->stocktake->fresh());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field === 'identifier' ? 'serializedIdentifier' : $field, $message);
                }
            }
        }
    }

    public function cancel(): void
    {
        (new CancelStocktake)($this->stocktake);
        $this->loadStocktake($this->stocktake->fresh());
    }

    private function loadStocktake(?Stocktake $stocktake = null): void
    {
        $this->stocktake = ($stocktake ?? $this->stocktake)->load([
            'store',
            'startedBy',
            'completedBy',
            'cancelledBy',
            'lines.acceptedObservation.countedBy',
            'lines.observations.countedBy',
            'observations.asset',
            'observations.stockLevel',
            'observations.countedBy',
        ]);

        foreach ($this->stocktake->lines as $line) {
            if ($line->acceptedObservation !== null) {
                $this->counts[$line->id] = (string) $line->acceptedObservation->counted_quantity;
            }
        }
    }

    public function lineDelta(StocktakeLine $line): ?string
    {
        return $line->observationDelta();
    }

    private function displayObservation(\App\Models\StocktakeObservation $observation): string
    {
        return $observation->asset_number_snapshot
            ?? $observation->serial_number_snapshot
            ?? $observation->barcode_snapshot
            ?? 'Serialized Asset';
    }

    private function displayObservationContext(\App\Models\StocktakeObservation $observation): string
    {
        $context = $observation->lifecycle_status_snapshot !== null
            ? ' — Lifecycle: '.ucfirst($observation->lifecycle_status_snapshot)
            : '';

        if ($observation->store_id_snapshot !== null && $observation->store_id_snapshot !== $this->stocktake->store_id) {
            $context .= ' — System Store #'.$observation->store_id_snapshot;
        }

        return $context;
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Stocktake #{{ $stocktake->id }}">
        <x-slot:breadcrumbs><a href="{{ route('stocktakes.index') }}" wire:navigate class="text-[var(--link)] hover:underline">Stocktakes</a></x-slot:breadcrumbs>
        <x-slot:meta><span class="s-badge s-badge-zinc">{{ $stocktake->status->label() }}</span></x-slot:meta>
        <x-slot:actions>
            @if($stocktake->isDraft())
                <button type="button" wire:click="start" wire:loading.attr="disabled" class="s-btn s-btn-sm s-btn-primary">Start counting</button>
            @elseif($stocktake->isInProgress())
                <button type="button" wire:click="complete" wire:confirm="Complete this Stocktake? Uncounted lines will remain evidence as not counted." class="s-btn s-btn-sm s-btn-primary">Complete</button>
                <button type="button" wire:click="cancel" wire:confirm="Cancel this Stocktake? Existing evidence will be retained." class="s-btn s-btn-sm s-btn-ghost text-[var(--red)]">Cancel</button>
            @endif
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex flex-col gap-6 px-6 py-4 max-md:px-5 max-sm:px-3">
        <x-signals.panel title="Evidence summary">
            @php
                $total = $stocktake->lines->count();
                $counted = $stocktake->lines->filter(fn ($line) => $line->isCounted())->count();
                $deltas = $stocktake->lines->filter(fn ($line) => $line->observationDelta() !== null && bccomp($line->observationDelta(), '0.00', 2) !== 0)->count();
            @endphp
            <div class="grid grid-cols-4 gap-4 max-md:grid-cols-2">
                <div><div class="text-xs uppercase text-[var(--text-muted)]">Store</div><div class="font-semibold">{{ $stocktake->store?->name }}</div></div>
                <div><div class="text-xs uppercase text-[var(--text-muted)]">Lines</div><div class="font-semibold">{{ $total }}</div></div>
                <div><div class="text-xs uppercase text-[var(--text-muted)]">Counted</div><div class="font-semibold">{{ $counted }} / {{ $total }}</div></div>
                <div><div class="text-xs uppercase text-[var(--text-muted)]">Non-zero deltas</div><div class="font-semibold">{{ $deltas }}</div></div>
            </div>
            <p class="mt-4 text-xs text-[var(--text-muted)]">The system quantity is the StockLevel.quantity_held snapshot at start. A count delta is evidence only; it is not a shortage or a stock correction.</p>
        </x-signals.panel>

        <x-signals.panel title="Bulk count workspace">
            @php
                $bulkLines = $stocktake->lines->filter(fn ($line) => $line->asset_id === null);
            @endphp
            @if($bulkLines->isEmpty())
                <p class="py-6 text-center text-sm text-[var(--text-muted)]">No eligible bulk StockLevels were found for this Store.</p>
            @else
                <div class="s-table-wrap">
                    <table class="s-table w-full">
                        <thead><tr><th>Product</th><th>System at start</th><th>Counted</th><th>Delta</th><th>Last operator</th><th></th></tr></thead>
                        <tbody>
                            @foreach($bulkLines as $line)
                                @php $observation = $line->acceptedObservation; $delta = $line->observationDelta(); @endphp
                                <tr wire:key="stocktake-line-{{ $line->id }}">
                                    <td><div class="font-medium">{{ $line->product_name_snapshot }}</div><div class="text-xs text-[var(--text-muted)]">{{ $line->stock_level_label_snapshot ?? 'Bulk stock' }}</div></td>
                                    <td>{{ $line->quantity_held_snapshot }}</td>
                                    <td>
                                        @if($stocktake->isInProgress())
                                            <input type="number" min="0" step="0.01" wire:model="counts.{{ $line->id }}" class="s-input w-28" placeholder="Not counted">
                                            @error("counts.{$line->id}")<div class="mt-1 text-xs text-[var(--red)]">{{ $message }}</div>@enderror
                                        @else
                                            {{ $observation?->counted_quantity ?? 'Not counted' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($delta !== null)
                                            <span class="font-semibold {{ bccomp($delta, '0.00', 2) === 0 ? 'text-[var(--green)]' : 'text-[var(--red)]' }}">{{ bccomp($delta, '0.00', 2) > 0 ? '+' : '' }}{{ $delta }}</span>
                                        @else
                                            <span class="text-[var(--text-muted)]">Not counted</span>
                                        @endif
                                    </td>
                                    <td class="text-xs">{{ $observation?->countedBy?->name ?? '—' }}<br>{{ $observation?->counted_at?->format('d M H:i') }}</td>
                                    <td class="text-right">
                                        @if($stocktake->isInProgress())
                                            <button type="button" wire:click="recordCount({{ $line->id }})" wire:loading.attr="disabled" class="s-btn s-btn-xs s-btn-primary">{{ $observation ? 'Recount' : 'Save' }}</button>
                                        @endif
                                    </td>
                                </tr>
                                @if($stocktake->isCompleted() && $line->observations->count() > 1)
                                    <tr wire:key="stocktake-history-{{ $line->id }}"><td colspan="6" class="bg-[var(--surface-muted)] text-xs">{{ $line->observations->count() }} observations retained; the latest accepted observation is shown above.</td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-signals.panel>

        <x-signals.panel title="Serialized scan workspace">
            @if($stocktake->isInProgress())
                <form wire:submit="scanSerialized" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1">
                        <flux:field>
                            <flux:label>Scan barcode, asset number, or serial number</flux:label>
                            <flux:input wire:model="serializedIdentifier" autofocus autocomplete="off" placeholder="Scan or type identifier" />
                            @error('serializedIdentifier')<flux:error>{{ $message }}</flux:error>@enderror
                        </flux:field>
                    </div>
                    <div class="min-w-64 flex-1">
                        <flux:field>
                            <flux:label>Note (optional)</flux:label>
                            <flux:input wire:model="serializedNotes" autocomplete="off" placeholder="Optional context" />
                        </flux:field>
                    </div>
                    <flux:button type="submit" wire:loading.attr="disabled" variant="primary">Record scan</flux:button>
                </form>
            @endif

            @if($scanFeedback !== [])
                <div class="mt-4 space-y-2" aria-live="polite">
                    @foreach(array_reverse($scanFeedback) as $feedback)
                        <div class="rounded border px-3 py-2 text-sm {{ $feedback['type'] === 'expected' ? 'border-green-300 text-green-700' : ($feedback['type'] === 'unresolved' ? 'border-yellow-300 text-yellow-700' : 'border-orange-300 text-orange-700') }}">
                            {{ $feedback['message'] }}
                        </div>
                    @endforeach
                </div>
            @endif

            @php
                $serializedLines = $stocktake->lines->filter(fn ($line) => $line->asset_id !== null);
            @endphp
            <div class="mt-5 flex items-center justify-between text-sm text-[var(--text-muted)]">
                <span>Expected serialized Assets</span>
                <span>{{ $serializedLines->filter(fn ($line) => $line->isCounted())->count() }} / {{ $serializedLines->count() }} observed</span>
            </div>
            @if($serializedLines->isNotEmpty())
                <div class="s-table-wrap mt-3">
                    <table class="s-table w-full">
                        <thead><tr><th>Product / Asset</th><th>Identity</th><th>Expected container</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($serializedLines as $line)
                                @php
                                    $observation = $line->acceptedObservation;
                                @endphp
                                <tr wire:key="serialized-line-{{ $line->id }}">
                                    <td><div class="font-medium">{{ $line->product_name_snapshot }}</div><div class="text-xs text-[var(--text-muted)]">Asset #{{ $line->asset_id }}</div></td>
                                    <td class="font-mono text-xs">{{ $line->asset_number_snapshot ?? $line->serial_number_snapshot ?? $line->barcode_snapshot ?? '—' }}</td>
                                    <td class="text-xs">{{ $line->container_name_snapshot ?? $line->container_barcode_snapshot ?? 'None recorded' }}</td>
                                    <td class="text-sm">
                                        {{ $observation ? 'Observed' : 'Not observed' }}
                                        @if($line->lifecycle_status_snapshot && $line->lifecycle_status_snapshot !== 'active')
                                            <div class="text-xs text-[var(--text-muted)]">Lifecycle: {{ ucfirst($line->lifecycle_status_snapshot) }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @php
                $serializedFindings = $stocktake->observations->filter(fn ($observation) => $observation->isSerializedScan() || $observation->isUnresolved());
            @endphp
            @if($serializedFindings->isNotEmpty())
                <div class="mt-5">
                    <h3 class="text-sm font-semibold">Serialized findings</h3>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach($serializedFindings->sortByDesc('counted_at')->take(10) as $finding)
                            <li class="text-[var(--text-muted)]">
                                {{ $finding->identifier ?? $finding->asset_number_snapshot ?? $finding->serial_number_snapshot ?? 'Serialized Asset' }}
                                — {{ $finding->isUnresolved() ? 'unresolved' : ($finding->line ? 'expected' : 'unexpected') }}
                                — {{ $finding->countedBy?->name ?? '—' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-signals.panel>
    </div>
</section>

<?php

declare(strict_types=1);

namespace App\Domain\Stocktakes\Presentation\Livewire;

use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stocktakes\Actions\CancelStocktake;
use App\Domain\Stocktakes\Actions\CompleteStocktake;
use App\Domain\Stocktakes\Actions\RecordBulkStocktakeObservation;
use App\Domain\Stocktakes\Actions\RecordSerializedStocktakeObservation;
use App\Domain\Stocktakes\Actions\StartStocktake;
use App\Domain\Stocktakes\Data\RecordBulkStocktakeObservationData;
use App\Domain\Stocktakes\Models\Stocktake;
use App\Domain\Stocktakes\Models\StocktakeLine;
use App\Domain\Stocktakes\Models\StocktakeObservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Show extends Component
{
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

    private function displayObservation(StocktakeObservation $observation): string
    {
        return $observation->asset_number_snapshot
            ?? $observation->serial_number_snapshot
            ?? $observation->barcode_snapshot
            ?? 'Serialized Asset';
    }

    private function displayObservationContext(StocktakeObservation $observation): string
    {
        $context = $observation->lifecycle_status_snapshot !== null
            ? ' — Lifecycle: '.ucfirst($observation->lifecycle_status_snapshot)
            : '';

        if ($observation->store_id_snapshot !== null && $observation->store_id_snapshot !== $this->stocktake->store_id) {
            $context .= ' — System Store #'.$observation->store_id_snapshot;
        }

        return $context;
    }

    public function render(): View
    {
        return view('stocktakes::show');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Stocktakes\CancelStocktake;
use App\Actions\Stocktakes\CompleteStocktake;
use App\Actions\Stocktakes\CreateStocktake;
use App\Actions\Stocktakes\RecordBulkStocktakeObservation;
use App\Actions\Stocktakes\RecordSerializedStocktakeObservation;
use App\Actions\Stocktakes\StartStocktake;
use App\Data\Stocktakes\CreateStocktakeData;
use App\Data\Stocktakes\RecordBulkStocktakeObservationData;
use App\Http\Controllers\Api\Controller;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StocktakeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:read');

        $stocktakes = Stocktake::query()
            ->with(['store', 'startedBy', 'completedBy', 'cancelledBy'])
            ->whereIn('store_id', $this->accessibleStoreIds())
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 25));

        return $this->respondWithCollection(
            $stocktakes->getCollection()->map(fn (Stocktake $stocktake): array => $this->serializeStocktake($stocktake))->all(),
            'stocktakes',
            $stocktakes,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');

        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $stocktake = (new CreateStocktake)(CreateStocktakeData::from($data));

        return $this->respondWith($this->serializeStocktake($stocktake), 'stocktake', Response::HTTP_CREATED);
    }

    public function show(Stocktake $stocktake): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:read');
        $this->authorizeStocktake($stocktake);

        return $this->respondWith($this->serializeStocktake($stocktake->load([
            'store',
            'startedBy',
            'completedBy',
            'cancelledBy',
            'lines.acceptedObservation.countedBy',
            'lines.observations.countedBy',
            'lines.asset',
        ]), true), 'stocktake');
    }

    public function start(Stocktake $stocktake): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');
        $this->authorizeStocktake($stocktake);

        $result = (new StartStocktake)($stocktake);

        return $this->respondWith($this->serializeStocktake($result, true), 'stocktake');
    }

    public function count(Request $request, Stocktake $stocktake, StocktakeLine $line): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');
        $this->authorizeStocktake($stocktake);
        abort_unless($line->stocktake_id === $stocktake->id, Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'counted_quantity' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $observation = (new RecordBulkStocktakeObservation)(
            $line,
            RecordBulkStocktakeObservationData::from($data),
        );

        return $this->respondWith($observation->load('countedBy')->toArray(), 'stocktake_observation', Response::HTTP_CREATED);
    }

    public function scan(Request $request, Stocktake $stocktake): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');
        $this->authorizeStocktake($stocktake);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $observation = (new RecordSerializedStocktakeObservation)(
            $stocktake,
            $data['identifier'],
            $data['notes'] ?? null,
        );

        return $this->respondWith([
            'observation' => $observation->toArray(),
            'created' => $observation->wasRecentlyCreated,
            'duplicate' => ! $observation->wasRecentlyCreated,
            'expected' => $observation->stocktake_line_id !== null,
            'unresolved' => $observation->isUnresolved(),
            'lifecycle_status' => $observation->lifecycle_status_snapshot,
            'current_store_id' => $observation->store_id_snapshot,
        ], 'stocktake_scan', $observation->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function complete(Stocktake $stocktake): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');
        $this->authorizeStocktake($stocktake);

        $result = (new CompleteStocktake)($stocktake);

        return $this->respondWith($this->serializeStocktake($result, true), 'stocktake');
    }

    public function cancel(Stocktake $stocktake): JsonResponse
    {
        $this->authorizeApi('stock.count', 'stock:write');
        $this->authorizeStocktake($stocktake);

        $result = (new CancelStocktake)($stocktake);

        return $this->respondWith($this->serializeStocktake($result, true), 'stocktake');
    }

    /** @return list<int> */
    private function accessibleStoreIds(): array
    {
        $ids = request()->user()?->accessibleStoreIds();

        return $ids ?? Store::query()->pluck('id')->all();
    }

    private function authorizeStocktake(Stocktake $stocktake): void
    {
        abort_unless(in_array($stocktake->store_id, $this->accessibleStoreIds(), true), Response::HTTP_NOT_FOUND);
    }

    /** @return array<string, mixed> */
    private function serializeStocktake(Stocktake $stocktake, bool $withLines = false): array
    {
        $payload = [
            'id' => $stocktake->id,
            'store_id' => $stocktake->store_id,
            'store_name' => $stocktake->store?->name,
            'status' => $stocktake->status->value,
            'started_by_user_id' => $stocktake->started_by_user_id,
            'started_at' => $stocktake->started_at?->toIso8601String(),
            'completed_by_user_id' => $stocktake->completed_by_user_id,
            'completed_at' => $stocktake->completed_at?->toIso8601String(),
            'cancelled_by_user_id' => $stocktake->cancelled_by_user_id,
            'cancelled_at' => $stocktake->cancelled_at?->toIso8601String(),
            'notes' => $stocktake->notes,
        ];

        if ($withLines) {
            $payload['lines'] = $stocktake->lines->map(fn (StocktakeLine $line): array => [
                'id' => $line->id,
                'stock_level_id' => $line->stock_level_id,
                'asset_id' => $line->asset_id,
                'product_name' => $line->product_name_snapshot,
                'stock_level_label' => $line->stock_level_label_snapshot,
                'quantity_held_snapshot' => $line->quantity_held_snapshot,
                'asset_number' => $line->asset_number_snapshot,
                'serial_number' => $line->serial_number_snapshot,
                'barcode' => $line->barcode_snapshot,
                'lifecycle_status' => $line->lifecycle_status_snapshot,
                'repair_status' => $line->repair_status_snapshot,
                'inspection_status' => $line->inspection_status_snapshot,
                'container_name' => $line->container_name_snapshot,
                'container_barcode' => $line->container_barcode_snapshot,
                'accepted_observation_id' => $line->accepted_observation_id,
                'counted_quantity' => $line->acceptedObservation?->counted_quantity,
                'observation_delta' => $line->observationDelta(),
            ])->all();
        }

        return $payload;
    }
}

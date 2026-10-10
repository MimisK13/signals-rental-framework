<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\AdjustStockLevel;
use App\Actions\Products\ReverseStockTransaction;
use App\Data\Products\AdjustStockLevelData;
use App\Data\Products\StockTransactionData;
use App\Enums\TransactionType;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StockTransactionController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'transaction_type',
        'store_id',
        'source_id',
        'source_type',
        'manual',
        'transaction_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'transaction_type',
        'transaction_at',
        'quantity',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'stockLevel',
        'store',
        'source',
    ];

    /**
     * List stock transactions for a stock level.
     *
     * Supports Ransack `q[...]` filtering and `sort` on the whitelisted fields.
     */
    #[ApiResponse(200, 'Stock transactions list')]
    public function index(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $stockLevel = $this->resolveStockLevel($productId, $stockLevelId);

        $query = StockTransaction::query()->where('stock_level_id', $stockLevel->id);
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $this->hasExplicitSort($request)) {
            $query->orderByDesc('transaction_at');
        }

        $transactions = $this->paginateQuery($query, $request);

        $items = $transactions->getCollection()->map(
            fn (StockTransaction $t): array => StockTransactionData::fromModel($t)->toArray()
        )->all();

        return response()->json([
            'stock_transactions' => $items,
            'meta' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'page' => $transactions->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single stock transaction.
     */
    #[ApiResponse(200, 'Stock transaction details')]
    public function show(int $productId, int $stockLevelId, StockTransaction $stockTransaction): JsonResponse
    {
        $this->authorizeApi('stock.view', 'stock:read');

        $this->resolveStockLevel($productId, $stockLevelId);

        abort_unless($stockTransaction->stock_level_id === (int) $stockLevelId, 404);

        return $this->respondWith(
            StockTransactionData::fromModel($stockTransaction)->toArray(),
            'stock_transaction',
        );
    }

    /**
     * Create an operator stock adjustment through the legacy transaction route.
     *
     * The route remains for compatibility, but only D2 adjustment types are
     * accepted. Business and system transaction types use dedicated workflows.
     */
    #[ApiResponse(201, 'Stock adjustment created')]
    public function store(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $result = $this->createAdjustment($request, $this->resolveStockLevel($productId, $stockLevelId));

        return $this->respondWith(
            $result->toArray(),
            'stock_transaction',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Create an explicit operator stock adjustment.
     */
    #[ApiResponse(201, 'Stock adjustment created')]
    public function adjust(Request $request, int $productId, int $stockLevelId): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $result = $this->createAdjustment($request, $this->resolveStockLevel($productId, $stockLevelId));

        return $this->respondWith(
            $result->toArray(),
            'stock_transaction',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Delete (reverse) a stock transaction.
     *
     * Reverses the transaction's effect on held stock while preserving the ledger row.
     */
    #[ApiResponse(204, 'Stock transaction deleted')]
    public function destroy(Request $request, int $productId, int $stockLevelId, StockTransaction $stockTransaction): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');

        $this->resolveStockLevel($productId, $stockLevelId);

        abort_unless($stockTransaction->stock_level_id === (int) $stockLevelId, 404);

        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ])['reason'];

        (new ReverseStockTransaction)($stockTransaction, $reason);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Create a compensating transaction while preserving the original fact.
     */
    #[ApiResponse(201, 'Stock transaction reversed')]
    public function reverse(Request $request, int $productId, int $stockLevelId, StockTransaction $stockTransaction): JsonResponse
    {
        $this->authorizeApi('stock.adjust', 'stock:write');
        $this->resolveStockLevel($productId, $stockLevelId);
        abort_unless($stockTransaction->stock_level_id === (int) $stockLevelId, 404);

        $reason = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ])['reason'];

        $result = (new ReverseStockTransaction)($stockTransaction, $reason);

        return $this->respondWith(
            $result->toArray(),
            'stock_transaction',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Resolve the stock level scoped to the routed product, 404ing on mismatch.
     */
    private function resolveStockLevel(int $productId, int $stockLevelId): StockLevel
    {
        return StockLevel::query()
            ->where('id', $stockLevelId)
            ->where('product_id', $productId)
            ->firstOrFail();
    }

    private function createAdjustment(Request $request, StockLevel $stockLevel): StockTransactionData
    {
        if (! $request->has('transaction_type') && $request->has('type')) {
            $type = strtolower((string) $request->input('type'));
            $transactionType = collect(TransactionType::cases())
                ->first(fn (TransactionType $candidate): bool => Str::snake($candidate->name) === $type);

            $request->merge(['transaction_type' => $transactionType?->value]);
        }

        $validated = $request->validate(AdjustStockLevelData::rules());
        unset($validated['type']);

        return (new AdjustStockLevel)($stockLevel, AdjustStockLevelData::from($validated));
    }
}

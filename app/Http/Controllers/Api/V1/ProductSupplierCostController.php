<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SupplierCosts\CreateSupplierCost;
use App\Actions\SupplierCosts\DeleteSupplierCost;
use App\Actions\SupplierCosts\SetPreferredSupplier;
use App\Actions\SupplierCosts\UpdateSupplierCost;
use App\Data\SupplierCosts\CreateSupplierCostData;
use App\Data\SupplierCosts\ProductSupplierCostData;
use App\Data\SupplierCosts\UpdateSupplierCostData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\Product;
use App\Models\ProductSupplierCost;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ProductSupplierCostController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'supplier_id',
        'store_id',
        'cost_basis',
        'currency_code',
        'supplier_sku',
        'lead_time_days',
        'is_preferred',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'supplier_id',
        'store_id',
        'cost',
        'cost_basis',
        'currency_code',
        'lead_time_days',
        'is_preferred',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'supplier',
        'store',
    ];

    /**
     * List supplier costs for a product.
     */
    #[ApiResponse(200, 'Supplier cost list', type: 'array{supplier_costs: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request, Product $product): JsonResponse
    {
        $this->authorizeRead();

        $query = $product->supplierCosts()->getQuery();
        $query = $this->applyIncludes($query, $request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (ProductSupplierCost $cost): array => ProductSupplierCostData::fromModel($cost)->toArray())
            ->all();

        return response()->json([
            'supplier_costs' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Show a single supplier cost for a product.
     */
    #[ApiResponse(200, 'Supplier cost details', type: 'array{supplier_cost: array<string, mixed>}')]
    public function show(Product $product, ProductSupplierCost $supplierCost): JsonResponse
    {
        $this->authorizeRead();
        $this->ensureBelongsToProduct($product, $supplierCost);

        $supplierCost->load(['supplier', 'store']);

        return $this->respondWith(ProductSupplierCostData::fromModel($supplierCost)->toArray(), 'supplier_cost');
    }

    /**
     * Create a supplier cost for a product.
     */
    #[ApiResponse(201, 'Supplier cost created', type: 'array{supplier_cost: array<string, mixed>}')]
    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeWrite();

        $request->merge(['product_id' => $product->id]);
        $validated = $request->validate(CreateSupplierCostData::rules());
        $dto = CreateSupplierCostData::from($validated);

        $result = (new CreateSupplierCost)($dto);

        return $this->respondWith($result->toArray(), 'supplier_cost', Response::HTTP_CREATED);
    }

    /**
     * Update a supplier cost for a product.
     */
    #[ApiResponse(200, 'Supplier cost updated', type: 'array{supplier_cost: array<string, mixed>}')]
    public function update(Request $request, Product $product, ProductSupplierCost $supplierCost): JsonResponse
    {
        Gate::authorize('update', $supplierCost);
        $this->ensureTokenAbility('products:write');

        $this->ensureBelongsToProduct($product, $supplierCost);

        $validated = $request->validate(UpdateSupplierCostData::rules());
        $dto = UpdateSupplierCostData::from([
            ...$validated,
            'store_id_provided' => $request->has('store_id'),
            'supplier_sku_provided' => $request->has('supplier_sku'),
            'lead_time_days_provided' => $request->has('lead_time_days'),
            'metadata_provided' => $request->has('metadata'),
        ]);

        $result = (new UpdateSupplierCost)($supplierCost, $dto);

        return $this->respondWith($result->toArray(), 'supplier_cost');
    }

    /**
     * Delete a supplier cost from a product.
     */
    #[ApiResponse(204, 'Supplier cost removed')]
    public function destroy(Product $product, ProductSupplierCost $supplierCost): JsonResponse
    {
        Gate::authorize('delete', $supplierCost);
        $this->ensureTokenAbility('products:write');

        $this->ensureBelongsToProduct($product, $supplierCost);

        (new DeleteSupplierCost)($supplierCost);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Mark a supplier cost as the preferred supplier for its product/store/basis scope.
     */
    #[ApiResponse(200, 'Preferred supplier cost', type: 'array{supplier_cost: array<string, mixed>}')]
    public function setPreferred(Product $product, ProductSupplierCost $supplierCost): JsonResponse
    {
        Gate::authorize('setPreferred', $supplierCost);
        $this->ensureTokenAbility('products:write');

        $this->ensureBelongsToProduct($product, $supplierCost);

        $result = (new SetPreferredSupplier)($supplierCost);

        return $this->respondWith($result->toArray(), 'supplier_cost');
    }

    private function authorizeRead(): void
    {
        Gate::authorize('viewAny', ProductSupplierCost::class);
        $this->ensureTokenAbility('products:read');
    }

    private function authorizeWrite(): void
    {
        Gate::authorize('create', ProductSupplierCost::class);
        $this->ensureTokenAbility('products:write');
    }

    private function ensureTokenAbility(string $ability): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = request()->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability)) {
            abort(Response::HTTP_FORBIDDEN, "Token does not have the required ability: {$ability}");
        }
    }

    private function ensureBelongsToProduct(Product $product, ProductSupplierCost $supplierCost): void
    {
        if ($supplierCost->product_id !== $product->id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}

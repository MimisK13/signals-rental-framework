<?php

declare(strict_types=1);

namespace App\Domain\Products\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Products\Actions\CreateProductGroup;
use App\Domain\Products\Actions\DeleteProductGroup;
use App\Domain\Products\Actions\UpdateProductGroup;
use App\Domain\Products\Data\CreateProductGroupData;
use App\Domain\Products\Data\ProductGroupData;
use App\Domain\Products\Data\UpdateProductGroupData;
use App\Domain\Products\Models\ProductGroup;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'parent_id',
    ];

    protected ?string $customFieldModule = 'ProductGroup';

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'parent',
        'children',
        'customFieldValues',
    ];

    /** @var list<string> */
    protected array $defaultIncludes = [
        'customFieldValues',
    ];

    protected function modelClass(): string
    {
        return ProductGroup::class;
    }

    protected function responseDataClass(): string
    {
        return ProductGroupData::class;
    }

    protected function createDataClass(): string
    {
        return CreateProductGroupData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateProductGroupData::class;
    }

    protected function createActionClass(): string
    {
        return CreateProductGroup::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateProductGroup::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteProductGroup::class;
    }

    protected function singularKey(): string
    {
        return 'product_group';
    }

    protected function pluralKey(): string
    {
        return 'product_groups';
    }

    protected function entityType(): string
    {
        return 'product_groups';
    }

    protected function permissions(): array
    {
        return ['view' => 'products.view', 'create' => 'products.create', 'edit' => 'products.edit', 'delete' => 'products.delete'];
    }

    protected function abilities(): array
    {
        return ['read' => 'products:read', 'write' => 'products:write'];
    }

    /**
     * List product groups with filtering, sorting, and pagination.
     *
     * Supports `view_id` query parameter to apply a saved custom view.
     * View filters merge with explicit `q` params (explicit params take priority).
     * View sort applies only when no explicit `sort` param is given.
     */
    #[ApiResponse(200, 'Paginated product group list', type: 'array{product_groups: list<array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, custom_fields: array<string, mixed>, created_at: string, updated_at: string}>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single product group.
     */
    #[ApiResponse(200, 'Product group details', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, custom_fields: array<string, mixed>, created_at: string, updated_at: string}}')]
    public function show(Request $request, ProductGroup $productGroup): JsonResponse
    {
        return $this->resourceShow($request, $productGroup);
    }

    /**
     * Create a new product group.
     */
    #[ApiResponse(201, 'Product group created', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, created_at: string, updated_at: string}}')]
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an existing product group.
     */
    #[ApiResponse(200, 'Product group updated', type: 'array{product_group: array{id: int, name: string, description: string|null, parent_id: int|null, sort_order: int, created_at: string, updated_at: string}}')]
    public function update(Request $request, ProductGroup $productGroup): JsonResponse
    {
        return $this->resourceUpdate($request, $productGroup);
    }

    /**
     * Delete a product group.
     */
    #[ApiResponse(204, 'Product group deleted')]
    public function destroy(ProductGroup $productGroup): JsonResponse
    {
        return $this->resourceDestroy($productGroup);
    }
}

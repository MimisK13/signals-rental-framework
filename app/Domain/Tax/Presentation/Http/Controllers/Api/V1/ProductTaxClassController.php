<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Tax\Actions\CreateProductTaxClass;
use App\Domain\Tax\Actions\DeleteProductTaxClass;
use App\Domain\Tax\Actions\UpdateProductTaxClass;
use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\TaxClassData;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\ProductTaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductTaxClassController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return ProductTaxClass::class;
    }

    protected function responseDataClass(): string
    {
        return TaxClassData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxClassData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxClassData::class;
    }

    protected function createActionClass(): string
    {
        return CreateProductTaxClass::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateProductTaxClass::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteProductTaxClass::class;
    }

    protected function singularKey(): string
    {
        return 'product_tax_class';
    }

    protected function pluralKey(): string
    {
        return 'product_tax_classes';
    }

    protected function entityType(): string
    {
        return 'product_tax_classes';
    }

    protected function permissions(): array
    {
        return ['view' => 'tax-classes.view', 'create' => 'tax-classes.manage', 'edit' => 'tax-classes.manage', 'delete' => 'tax-classes.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'tax-classes:read', 'write' => 'tax-classes:write'];
    }

    /**
     * List product tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single product tax class.
     */
    public function show(Request $request, ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceShow($request, $productTaxClass);
    }

    /**
     * Create a product tax class.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update a product tax class.
     */
    public function update(Request $request, ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceUpdate($request, $productTaxClass);
    }

    /**
     * Delete a product tax class.
     */
    public function destroy(ProductTaxClass $productTaxClass): JsonResponse
    {
        return $this->resourceDestroy($productTaxClass);
    }
}

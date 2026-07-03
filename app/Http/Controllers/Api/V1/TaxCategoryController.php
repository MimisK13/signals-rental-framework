<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tax\CreateTaxCategory;
use App\Actions\Tax\DeleteTaxCategory;
use App\Actions\Tax\UpdateTaxCategory;
use App\Data\Tax\CreateTaxCategoryData;
use App\Data\Tax\TaxCategoryData;
use App\Data\Tax\UpdateTaxCategoryData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\TaxCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxCategoryController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'slug',
        'type',
        'is_active',
        'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'type',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return TaxCategory::class;
    }

    protected function responseDataClass(): string
    {
        return TaxCategoryData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxCategoryData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxCategoryData::class;
    }

    protected function createActionClass(): string
    {
        return CreateTaxCategory::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateTaxCategory::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteTaxCategory::class;
    }

    protected function singularKey(): string
    {
        return 'tax_category';
    }

    protected function pluralKey(): string
    {
        return 'tax_categories';
    }

    protected function entityType(): string
    {
        return 'tax_categories';
    }

    protected function permissions(): array
    {
        return [
            'view' => 'tax-classes.view',
            'create' => 'tax-classes.manage',
            'edit' => 'tax-classes.manage',
            'delete' => 'tax-classes.manage',
        ];
    }

    protected function abilities(): array
    {
        return ['read' => 'tax-classes:read', 'write' => 'tax-classes:write'];
    }

    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    public function show(Request $request, TaxCategory $taxCategory): JsonResponse
    {
        return $this->resourceShow($request, $taxCategory);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, TaxCategory $taxCategory): JsonResponse
    {
        return $this->resourceUpdate($request, $taxCategory);
    }

    public function destroy(TaxCategory $taxCategory): JsonResponse
    {
        return $this->resourceDestroy($taxCategory);
    }
}

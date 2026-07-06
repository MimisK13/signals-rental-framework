<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\CreateDiscountCategory;
use App\Actions\Pricing\DeleteDiscountCategory;
use App\Actions\Pricing\UpdateDiscountCategory;
use App\Data\Pricing\CreateDiscountCategoryData;
use App\Data\Pricing\DiscountCategoryData;
use App\Data\Pricing\UpdateDiscountCategoryData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\DiscountCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCategoryController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedIncludes = [
        'rules',
    ];

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return DiscountCategory::class;
    }

    protected function responseDataClass(): string
    {
        return DiscountCategoryData::class;
    }

    protected function createDataClass(): string
    {
        return CreateDiscountCategoryData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateDiscountCategoryData::class;
    }

    protected function createActionClass(): string
    {
        return CreateDiscountCategory::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateDiscountCategory::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteDiscountCategory::class;
    }

    protected function singularKey(): string
    {
        return 'discount_category';
    }

    protected function pluralKey(): string
    {
        return 'discount_categories';
    }

    protected function entityType(): string
    {
        return 'discount_categories';
    }

    protected function permissions(): array
    {
        return [
            'view' => 'pricing.view',
            'create' => 'pricing.manage',
            'edit' => 'pricing.manage',
            'delete' => 'pricing.manage',
        ];
    }

    protected function abilities(): array
    {
        return ['read' => 'pricing:read', 'write' => 'pricing:write'];
    }

    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    public function show(Request $request, DiscountCategory $discountCategory): JsonResponse
    {
        $this->applyIncludes(DiscountCategory::query(), $request, $discountCategory);

        return $this->resourceShow($request, $discountCategory);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, DiscountCategory $discountCategory): JsonResponse
    {
        return $this->resourceUpdate($request, $discountCategory);
    }

    public function destroy(DiscountCategory $discountCategory): JsonResponse
    {
        return $this->resourceDestroy($discountCategory);
    }
}

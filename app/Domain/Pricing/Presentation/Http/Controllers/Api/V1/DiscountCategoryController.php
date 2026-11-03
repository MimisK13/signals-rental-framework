<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Http\Controllers\Api\V1;

use App\Domain\Pricing\Actions\CreateDiscountCategory;
use App\Domain\Pricing\Actions\DeleteDiscountCategory;
use App\Domain\Pricing\Actions\UpdateDiscountCategory;
use App\Domain\Pricing\Data\CreateDiscountCategoryData;
use App\Domain\Pricing\Data\DiscountCategoryData;
use App\Domain\Pricing\Data\UpdateDiscountCategoryData;
use App\Domain\Pricing\Models\DiscountCategory;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
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

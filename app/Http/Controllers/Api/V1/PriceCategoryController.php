<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\CreatePriceCategory;
use App\Actions\Pricing\DeletePriceCategory;
use App\Actions\Pricing\UpdatePriceCategory;
use App\Data\Pricing\CreatePriceCategoryData;
use App\Data\Pricing\PriceCategoryData;
use App\Data\Pricing\UpdatePriceCategoryData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\PriceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceCategoryController extends Controller
{
    use FiltersQueries, ResourceActions;

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
        return PriceCategory::class;
    }

    protected function responseDataClass(): string
    {
        return PriceCategoryData::class;
    }

    protected function createDataClass(): string
    {
        return CreatePriceCategoryData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdatePriceCategoryData::class;
    }

    protected function createActionClass(): string
    {
        return CreatePriceCategory::class;
    }

    protected function updateActionClass(): string
    {
        return UpdatePriceCategory::class;
    }

    protected function deleteActionClass(): string
    {
        return DeletePriceCategory::class;
    }

    protected function singularKey(): string
    {
        return 'price_category';
    }

    protected function pluralKey(): string
    {
        return 'price_categories';
    }

    protected function entityType(): string
    {
        return 'price_categories';
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

    /**
     * @template TModel of PriceCategory
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyIncludes(Builder $query, Request $request, ?Model $model = null): Builder
    {
        if ($request->boolean('include_tiers', true) || str_contains((string) $request->query('include', ''), 'tiers')) {
            $query->with('tiers');
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    public function show(Request $request, PriceCategory $priceCategory): JsonResponse
    {
        $priceCategory->load('tiers');

        return $this->resourceShow($request, $priceCategory);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, PriceCategory $priceCategory): JsonResponse
    {
        return $this->resourceUpdate($request, $priceCategory);
    }

    public function destroy(PriceCategory $priceCategory): JsonResponse
    {
        return $this->resourceDestroy($priceCategory);
    }
}

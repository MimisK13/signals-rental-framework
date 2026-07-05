<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\CreateDiscountRule;
use App\Actions\Pricing\DeleteDiscountRule;
use App\Actions\Pricing\UpdateDiscountRule;
use App\Data\Pricing\CreateDiscountRuleData;
use App\Data\Pricing\DiscountRuleData;
use App\Data\Pricing\UpdateDiscountRuleData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\DiscountRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountRuleController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'discount_category_id',
        'discount_percent',
        'sort_order',
        'valid_from',
        'valid_to',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'sort_order',
        'discount_percent',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return DiscountRule::class;
    }

    protected function responseDataClass(): string
    {
        return DiscountRuleData::class;
    }

    protected function createDataClass(): string
    {
        return CreateDiscountRuleData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateDiscountRuleData::class;
    }

    protected function createActionClass(): string
    {
        return CreateDiscountRule::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateDiscountRule::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteDiscountRule::class;
    }

    protected function singularKey(): string
    {
        return 'discount_rule';
    }

    protected function pluralKey(): string
    {
        return 'discount_rules';
    }

    protected function entityType(): string
    {
        return 'discount_rules';
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

    public function show(Request $request, DiscountRule $discountRule): JsonResponse
    {
        return $this->resourceShow($request, $discountRule);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, DiscountRule $discountRule): JsonResponse
    {
        return $this->resourceUpdate($request, $discountRule);
    }

    public function destroy(DiscountRule $discountRule): JsonResponse
    {
        return $this->resourceDestroy($discountRule);
    }
}

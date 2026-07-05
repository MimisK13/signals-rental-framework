<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\CreateSurchargeGroup;
use App\Actions\Pricing\DeleteSurchargeGroup;
use App\Actions\Pricing\UpdateSurchargeGroup;
use App\Data\Pricing\CreateSurchargeGroupData;
use App\Data\Pricing\SurchargeGroupData;
use App\Data\Pricing\UpdateSurchargeGroupData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\SurchargeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurchargeGroupController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return SurchargeGroup::class;
    }

    protected function responseDataClass(): string
    {
        return SurchargeGroupData::class;
    }

    protected function createDataClass(): string
    {
        return CreateSurchargeGroupData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateSurchargeGroupData::class;
    }

    protected function createActionClass(): string
    {
        return CreateSurchargeGroup::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateSurchargeGroup::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteSurchargeGroup::class;
    }

    protected function singularKey(): string
    {
        return 'surcharge_group';
    }

    protected function pluralKey(): string
    {
        return 'surcharge_groups';
    }

    protected function entityType(): string
    {
        return 'surcharge_groups';
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

    public function show(Request $request, SurchargeGroup $surchargeGroup): JsonResponse
    {
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        $surchargeGroup->load('surcharges.productTaxClass');

        return $this->respondWith(
            SurchargeGroupData::fromModel($surchargeGroup, includeSurcharges: true)->toArray(),
            $this->singularKey(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, SurchargeGroup $surchargeGroup): JsonResponse
    {
        return $this->resourceUpdate($request, $surchargeGroup);
    }

    public function destroy(SurchargeGroup $surchargeGroup): JsonResponse
    {
        return $this->resourceDestroy($surchargeGroup);
    }
}

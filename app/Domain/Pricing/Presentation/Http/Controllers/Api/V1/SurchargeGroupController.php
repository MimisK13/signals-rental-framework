<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Http\Controllers\Api\V1;

use App\Domain\Pricing\Actions\CreateSurchargeGroup;
use App\Domain\Pricing\Actions\DeleteSurchargeGroup;
use App\Domain\Pricing\Actions\UpdateSurchargeGroup;
use App\Domain\Pricing\Data\CreateSurchargeGroupData;
use App\Domain\Pricing\Data\SurchargeGroupData;
use App\Domain\Pricing\Data\UpdateSurchargeGroupData;
use App\Domain\Pricing\Models\SurchargeGroup;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
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

<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Http\Controllers\Api\V1;

use App\Domain\Pricing\Actions\CreateSurcharge;
use App\Domain\Pricing\Actions\DeleteSurcharge;
use App\Domain\Pricing\Actions\UpdateSurcharge;
use App\Domain\Pricing\Data\CreateSurchargeData;
use App\Domain\Pricing\Data\SurchargeData;
use App\Domain\Pricing\Data\UpdateSurchargeData;
use App\Domain\Pricing\Models\Surcharge;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurchargeController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'surcharge_group_id',
        'name',
        'is_default',
        'product_tax_class_id',
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
        return Surcharge::class;
    }

    protected function responseDataClass(): string
    {
        return SurchargeData::class;
    }

    protected function createDataClass(): string
    {
        return CreateSurchargeData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateSurchargeData::class;
    }

    protected function createActionClass(): string
    {
        return CreateSurcharge::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateSurcharge::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteSurcharge::class;
    }

    protected function singularKey(): string
    {
        return 'surcharge';
    }

    protected function pluralKey(): string
    {
        return 'surcharges';
    }

    protected function entityType(): string
    {
        return 'surcharges';
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

    public function show(Request $request, Surcharge $surcharge): JsonResponse
    {
        return $this->resourceShow($request, $surcharge->load('productTaxClass'));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, Surcharge $surcharge): JsonResponse
    {
        return $this->resourceUpdate($request, $surcharge);
    }

    public function destroy(Surcharge $surcharge): JsonResponse
    {
        return $this->resourceDestroy($surcharge);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tax\CreateTaxZone;
use App\Actions\Tax\DeleteTaxZone;
use App\Actions\Tax\UpdateTaxZone;
use App\Data\Tax\CreateTaxZoneData;
use App\Data\Tax\TaxZoneData;
use App\Data\Tax\UpdateTaxZoneData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\TaxZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxZoneController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'slug',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'priority',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return TaxZone::class;
    }

    protected function responseDataClass(): string
    {
        return TaxZoneData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxZoneData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxZoneData::class;
    }

    protected function createActionClass(): string
    {
        return CreateTaxZone::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateTaxZone::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteTaxZone::class;
    }

    protected function singularKey(): string
    {
        return 'tax_zone';
    }

    protected function pluralKey(): string
    {
        return 'tax_zones';
    }

    protected function entityType(): string
    {
        return 'tax_zones';
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

    public function show(Request $request, TaxZone $taxZone): JsonResponse
    {
        return $this->resourceShow($request, $taxZone);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, TaxZone $taxZone): JsonResponse
    {
        return $this->resourceUpdate($request, $taxZone);
    }

    public function destroy(TaxZone $taxZone): JsonResponse
    {
        return $this->resourceDestroy($taxZone);
    }
}

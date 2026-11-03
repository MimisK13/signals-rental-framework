<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Tax\Actions\CreateTaxZone;
use App\Domain\Tax\Actions\DeleteTaxZone;
use App\Domain\Tax\Actions\UpdateTaxZone;
use App\Domain\Tax\Data\CreateTaxZoneData;
use App\Domain\Tax\Data\TaxZoneData;
use App\Domain\Tax\Data\UpdateTaxZoneData;
use App\Domain\Tax\Models\TaxZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaxZoneController extends Controller
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

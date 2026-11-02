<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Http\Controllers\Api\V1;

use App\Domain\Tax\Actions\CreateTaxRate;
use App\Domain\Tax\Actions\DeleteTaxRate;
use App\Domain\Tax\Actions\UpdateTaxRate;
use App\Domain\Tax\Data\CreateTaxRateData;
use App\Domain\Tax\Data\TaxRateData;
use App\Domain\Tax\Data\UpdateTaxRateData;
use App\Domain\Tax\Models\TaxRate;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaxRateController extends Controller
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
        'rate',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return TaxRate::class;
    }

    protected function responseDataClass(): string
    {
        return TaxRateData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxRateData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxRateData::class;
    }

    protected function createActionClass(): string
    {
        return CreateTaxRate::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateTaxRate::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteTaxRate::class;
    }

    protected function singularKey(): string
    {
        return 'tax_rate';
    }

    protected function pluralKey(): string
    {
        return 'tax_rates';
    }

    protected function entityType(): string
    {
        return 'tax_rates';
    }

    protected function permissions(): array
    {
        return ['view' => 'tax-classes.view', 'create' => 'tax-classes.manage', 'edit' => 'tax-classes.manage', 'delete' => 'tax-classes.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'tax-classes:read', 'write' => 'tax-classes:write'];
    }

    /**
     * List tax rates.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single tax rate.
     */
    public function show(Request $request, TaxRate $taxRate): JsonResponse
    {
        return $this->resourceShow($request, $taxRate);
    }

    /**
     * Create a tax rate.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update a tax rate.
     */
    public function update(Request $request, TaxRate $taxRate): JsonResponse
    {
        return $this->resourceUpdate($request, $taxRate);
    }

    /**
     * Delete a tax rate.
     */
    public function destroy(TaxRate $taxRate): JsonResponse
    {
        return $this->resourceDestroy($taxRate);
    }
}

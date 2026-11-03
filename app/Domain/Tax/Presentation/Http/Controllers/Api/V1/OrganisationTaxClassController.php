<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Tax\Actions\CreateOrganisationTaxClass;
use App\Domain\Tax\Actions\DeleteOrganisationTaxClass;
use App\Domain\Tax\Actions\UpdateOrganisationTaxClass;
use App\Domain\Tax\Data\CreateTaxClassData;
use App\Domain\Tax\Data\TaxClassData;
use App\Domain\Tax\Data\UpdateTaxClassData;
use App\Domain\Tax\Models\OrganisationTaxClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrganisationTaxClassController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return OrganisationTaxClass::class;
    }

    protected function responseDataClass(): string
    {
        return TaxClassData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxClassData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxClassData::class;
    }

    protected function createActionClass(): string
    {
        return CreateOrganisationTaxClass::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateOrganisationTaxClass::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteOrganisationTaxClass::class;
    }

    protected function singularKey(): string
    {
        return 'organisation_tax_class';
    }

    protected function pluralKey(): string
    {
        return 'organisation_tax_classes';
    }

    protected function entityType(): string
    {
        return 'organisation_tax_classes';
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
     * List organisation tax classes.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single organisation tax class.
     */
    public function show(Request $request, OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        return $this->resourceShow($request, $organisationTaxClass);
    }

    /**
     * Create an organisation tax class.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update an organisation tax class.
     */
    public function update(Request $request, OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        return $this->resourceUpdate($request, $organisationTaxClass);
    }

    /**
     * Delete an organisation tax class.
     */
    public function destroy(OrganisationTaxClass $organisationTaxClass): JsonResponse
    {
        return $this->resourceDestroy($organisationTaxClass);
    }
}

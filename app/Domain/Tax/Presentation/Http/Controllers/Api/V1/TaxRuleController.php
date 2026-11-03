<?php

declare(strict_types=1);

namespace App\Domain\Tax\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Tax\Actions\CreateTaxRule;
use App\Domain\Tax\Actions\DeleteTaxRule;
use App\Domain\Tax\Actions\UpdateTaxRule;
use App\Domain\Tax\Data\CreateTaxRuleData;
use App\Domain\Tax\Data\TaxRuleData;
use App\Domain\Tax\Data\UpdateTaxRuleData;
use App\Domain\Tax\Models\TaxRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaxRuleController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'organisation_tax_class_id',
        'product_tax_class_id',
        'tax_rate_id',
        'is_active',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'priority',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return TaxRule::class;
    }

    protected function responseDataClass(): string
    {
        return TaxRuleData::class;
    }

    protected function createDataClass(): string
    {
        return CreateTaxRuleData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateTaxRuleData::class;
    }

    protected function createActionClass(): string
    {
        return CreateTaxRule::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateTaxRule::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteTaxRule::class;
    }

    protected function singularKey(): string
    {
        return 'tax_rule';
    }

    protected function pluralKey(): string
    {
        return 'tax_rules';
    }

    protected function entityType(): string
    {
        return 'tax_rules';
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
     * List tax rules.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single tax rule.
     */
    public function show(Request $request, TaxRule $taxRule): JsonResponse
    {
        return $this->resourceShow($request, $taxRule);
    }

    /**
     * Create a tax rule.
     */
    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    /**
     * Update a tax rule.
     */
    public function update(Request $request, TaxRule $taxRule): JsonResponse
    {
        return $this->resourceUpdate($request, $taxRule);
    }

    /**
     * Delete a tax rule.
     */
    public function destroy(TaxRule $taxRule): JsonResponse
    {
        return $this->resourceDestroy($taxRule);
    }
}

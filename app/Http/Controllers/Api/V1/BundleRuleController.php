<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Pricing\CreateBundleRule;
use App\Actions\Pricing\DeleteBundleRule;
use App\Actions\Pricing\UpdateBundleRule;
use App\Data\Pricing\BundleRuleData;
use App\Data\Pricing\CreateBundleRuleData;
use App\Data\Pricing\UpdateBundleRuleData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\BundleRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleRuleController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
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
        return BundleRule::class;
    }

    protected function responseDataClass(): string
    {
        return BundleRuleData::class;
    }

    protected function createDataClass(): string
    {
        return CreateBundleRuleData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateBundleRuleData::class;
    }

    protected function createActionClass(): string
    {
        return CreateBundleRule::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateBundleRule::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteBundleRule::class;
    }

    protected function singularKey(): string
    {
        return 'bundle_rule';
    }

    protected function pluralKey(): string
    {
        return 'bundle_rules';
    }

    protected function entityType(): string
    {
        return 'bundle_rules';
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

    public function show(Request $request, BundleRule $bundleRule): JsonResponse
    {
        return $this->resourceShow($request, $bundleRule);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->resourceStore($request);
    }

    public function update(Request $request, BundleRule $bundleRule): JsonResponse
    {
        return $this->resourceUpdate($request, $bundleRule);
    }

    public function destroy(BundleRule $bundleRule): JsonResponse
    {
        return $this->resourceDestroy($bundleRule);
    }
}

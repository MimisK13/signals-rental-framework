<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Presentation\Http\Controllers\Api\V1;

use App\Domain\Pricing\Actions\CreateBundleRule;
use App\Domain\Pricing\Actions\DeleteBundleRule;
use App\Domain\Pricing\Actions\UpdateBundleRule;
use App\Domain\Pricing\Data\BundleRuleData;
use App\Domain\Pricing\Data\CreateBundleRuleData;
use App\Domain\Pricing\Data\UpdateBundleRuleData;
use App\Domain\Pricing\Models\BundleRule;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Concerns\ResourceActions;
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

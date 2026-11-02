<?php

declare(strict_types=1);

namespace App\Domain\CustomViews\Presentation\Http\Controllers\Api\V1;

use App\Domain\CustomViews\Actions\CloneCustomView;
use App\Domain\CustomViews\Actions\CreateCustomView;
use App\Domain\CustomViews\Actions\DeleteCustomView;
use App\Domain\CustomViews\Actions\UpdateCustomView;
use App\Domain\CustomViews\Data\CreateCustomViewData;
use App\Domain\CustomViews\Data\CustomViewData;
use App\Domain\CustomViews\Data\UpdateCustomViewData;
use App\Domain\CustomViews\Models\CustomView;
use App\Http\Controllers\Api\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class CustomViewController extends Controller
{
    /**
     * List custom views for an entity type, visible to the current user.
     *
     * @response array{custom_views: list<CustomViewData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomView::query();

        $entityType = $request->input('entity_type');
        if ($entityType) {
            $query->forEntity($entityType);
        }

        $query->visibleTo($request->user());
        $query->orderBy('visibility')
            ->orderBy('name');

        $views = $query->get()->map(
            fn (CustomView $view): array => CustomViewData::fromModel($view)->toArray()
        )->all();

        return $this->respondWithCollection($views, 'custom_views');
    }

    /**
     * Show a single custom view.
     *
     * @response array{custom_view: CustomViewData}
     */
    public function show(CustomView $customView): JsonResponse
    {
        abort_unless(Gate::allows('view', $customView), Response::HTTP_NOT_FOUND);

        return $this->respondWith(
            CustomViewData::fromModel($customView)->toArray(),
            'custom_view',
        );
    }

    /**
     * Create a new custom view.
     *
     * @response 201 array{custom_view: CustomViewData}
     */
    #[BodyParameter('visibility', required: false, type: "'personal'|'shared'")]
    #[BodyParameter('filters.*.value', required: false, type: 'mixed')]
    #[BodyParameter('filters.*.logic', required: false, type: "'and'|'or'|'nand'|'nor'")]
    #[BodyParameter('sort_direction', required: false, type: "'asc'|'desc'")]
    #[IgnoreResponse(200)]
    #[ApiResponse(status: 201, type: 'array{custom_view: CustomViewData}')]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(CreateCustomViewData::rules());
        $dto = CreateCustomViewData::from($validated);

        $result = (new CreateCustomView)($dto);

        return response()->json([
            'custom_view' => $result->toArray(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing custom view.
     *
     * @response array{custom_view: CustomViewData}
     */
    #[BodyParameter('visibility', required: false, type: "'personal'|'shared'")]
    #[BodyParameter('filters.*.value', required: false, type: 'mixed')]
    #[BodyParameter('filters.*.logic', required: false, type: "'and'|'or'|'nand'|'nor'")]
    #[BodyParameter('sort_direction', required: false, type: "'asc'|'desc'")]
    public function update(Request $request, CustomView $customView): JsonResponse
    {
        $validated = $request->validate(UpdateCustomViewData::rules());
        $dto = UpdateCustomViewData::from($validated);

        $result = (new UpdateCustomView)($customView, $dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_view',
        );
    }

    /**
     * Delete a custom view.
     */
    public function destroy(CustomView $customView): JsonResponse
    {
        (new DeleteCustomView)($customView);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Clone a custom view as a personal copy.
     *
     * @response 201 array{custom_view: CustomViewData}
     */
    #[IgnoreResponse(200)]
    #[ApiResponse(status: 201, type: 'array{custom_view: CustomViewData}')]
    public function clone(CustomView $customView): JsonResponse
    {
        abort_unless(Gate::allows('view', $customView), Response::HTTP_NOT_FOUND);

        $result = (new CloneCustomView)($customView);

        return response()->json([
            'custom_view' => $result->toArray(),
        ], Response::HTTP_CREATED);
    }
}

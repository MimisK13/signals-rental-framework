<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\CustomFields\Actions\CreateCustomFieldGroup;
use App\Domain\CustomFields\Actions\DeleteCustomFieldGroup;
use App\Domain\CustomFields\Actions\UpdateCustomFieldGroup;
use App\Domain\CustomFields\Data\CreateCustomFieldGroupData;
use App\Domain\CustomFields\Data\CustomFieldGroupData;
use App\Domain\CustomFields\Data\UpdateCustomFieldGroupData;
use App\Domain\CustomFields\Models\CustomFieldGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomFieldGroupController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
        'created_at',
    ];

    /**
     * List custom field groups.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('custom-fields.view', 'custom-fields:read');

        $query = CustomFieldGroup::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $groups = $paginator->getCollection()->map(
            fn (CustomFieldGroup $group): array => CustomFieldGroupData::fromModel($group)->toArray()
        )->all();

        return $this->respondWithCollection($groups, 'custom_field_groups', $paginator);
    }

    /**
     * Show a single custom field group.
     */
    public function show(CustomFieldGroup $customFieldGroup): JsonResponse
    {
        $this->authorizeApi('custom-fields.view', 'custom-fields:read');

        $customFieldGroup->load('customFields');

        return $this->respondWith(
            CustomFieldGroupData::fromModel($customFieldGroup)->toArray(),
            'custom_field_group',
        );
    }

    /**
     * Create a custom field group.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        $validated = $request->validate(CreateCustomFieldGroupData::rules());
        $dto = CreateCustomFieldGroupData::from($validated);

        $result = (new CreateCustomFieldGroup)($dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_field_group',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a custom field group.
     */
    public function update(Request $request, CustomFieldGroup $customFieldGroup): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        $validated = $request->validate(UpdateCustomFieldGroupData::rules());
        $dto = UpdateCustomFieldGroupData::from($validated);

        $result = (new UpdateCustomFieldGroup)($customFieldGroup, $dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_field_group',
        );
    }

    /**
     * Delete a custom field group.
     */
    public function destroy(CustomFieldGroup $customFieldGroup): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        (new DeleteCustomFieldGroup)($customFieldGroup);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

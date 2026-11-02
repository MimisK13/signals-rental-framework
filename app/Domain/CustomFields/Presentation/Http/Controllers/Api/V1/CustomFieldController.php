<?php

declare(strict_types=1);

namespace App\Domain\CustomFields\Presentation\Http\Controllers\Api\V1;

use App\Domain\CustomFields\Actions\CreateCustomField;
use App\Domain\CustomFields\Actions\DeleteCustomField;
use App\Domain\CustomFields\Actions\UpdateCustomField;
use App\Domain\CustomFields\Data\CreateCustomFieldData;
use App\Domain\CustomFields\Data\CustomFieldData;
use App\Domain\CustomFields\Data\UpdateCustomFieldData;
use App\Domain\CustomFields\Models\CustomField;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomFieldController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'module_type',
        'field_type',
        'is_active',
        'is_required',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'module_type',
        'sort_order',
        'created_at',
    ];

    /**
     * List custom fields with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('custom-fields.view', 'custom-fields:read');

        $query = CustomField::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);
        $paginator = $this->paginateQuery($query, $request);

        $fields = $paginator->getCollection()->map(
            fn (CustomField $field): array => CustomFieldData::fromModel($field)->toArray()
        )->all();

        return $this->respondWithCollection($fields, 'custom_fields', $paginator);
    }

    /**
     * Show a single custom field.
     */
    public function show(CustomField $customField): JsonResponse
    {
        $this->authorizeApi('custom-fields.view', 'custom-fields:read');

        return $this->respondWith(
            CustomFieldData::fromModel($customField)->toArray(),
            'custom_field',
        );
    }

    /**
     * Create a custom field.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        $validated = $request->validate(CreateCustomFieldData::rules());
        $dto = CreateCustomFieldData::from($validated);

        $result = (new CreateCustomField)($dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_field',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a custom field.
     */
    public function update(Request $request, CustomField $customField): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        $validated = $request->validate(UpdateCustomFieldData::rules());
        $dto = UpdateCustomFieldData::from($validated);

        $result = (new UpdateCustomField)($customField, $dto);

        return $this->respondWith(
            $result->toArray(),
            'custom_field',
        );
    }

    /**
     * Delete a custom field.
     */
    public function destroy(CustomField $customField): JsonResponse
    {
        $this->authorizeApi('custom-fields.manage', 'custom-fields:write');

        (new DeleteCustomField)($customField);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

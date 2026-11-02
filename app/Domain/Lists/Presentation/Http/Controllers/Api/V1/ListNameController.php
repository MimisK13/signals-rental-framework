<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Http\Controllers\Api\V1;

use App\Domain\Lists\Actions\CreateListName;
use App\Domain\Lists\Actions\DeleteListName;
use App\Domain\Lists\Actions\UpdateListName;
use App\Domain\Lists\Data\CreateListNameData;
use App\Domain\Lists\Data\ListNameData;
use App\Domain\Lists\Data\UpdateListNameData;
use App\Domain\Lists\Models\ListName;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ListNameController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'is_system',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'created_at',
    ];

    protected function modelClass(): string
    {
        return ListName::class;
    }

    protected function responseDataClass(): string
    {
        return ListNameData::class;
    }

    protected function createDataClass(): string
    {
        return CreateListNameData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateListNameData::class;
    }

    protected function createActionClass(): string
    {
        return CreateListName::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateListName::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteListName::class;
    }

    protected function singularKey(): string
    {
        return 'list_name';
    }

    protected function pluralKey(): string
    {
        return 'list_names';
    }

    protected function entityType(): string
    {
        return 'list_names';
    }

    protected function permissions(): array
    {
        return ['view' => 'list-values.view', 'create' => 'list-values.manage', 'edit' => 'list-values.manage', 'delete' => 'list-values.manage'];
    }

    protected function abilities(): array
    {
        return ['read' => 'static-data:read', 'write' => 'static-data:write'];
    }

    /**
     * List all list names.
     *
     * @response array{list_names: list<ListNameData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single list name with its values.
     *
     * @response array{list_name: ListNameData}
     */
    public function show(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $listName->load('values');

        return $this->respondWith(
            ListNameData::fromModel($listName)->toArray(),
            'list_name',
        );
    }

    /**
     * Create a list name.
     */
    #[ApiResponse(status: 201, type: 'array{list_name: ListNameData}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $validated = $request->validate(CreateListNameData::rules());
        $result = (new CreateListName)(CreateListNameData::from($validated));

        return $this->respondWith(
            $result->toArray(),
            'list_name',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a list name.
     *
     * The bound model id is supplied explicitly to both the validation rules
     * (so the unique rule ignores the current record) and the DTO, keeping
     * UpdateListNameData independent of the HTTP request/route.
     *
     * @response array{list_name: ListNameData}
     */
    public function update(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $validated = $request->validate(UpdateListNameData::rules($listName->id));
        $dto = UpdateListNameData::from([...$validated, 'list_name_id' => $listName->id]);

        $result = (new UpdateListName)($listName, $dto);

        return $this->respondWith(
            $result->toArray(),
            'list_name',
        );
    }

    /**
     * Delete a list name.
     */
    public function destroy(ListName $listName): JsonResponse
    {
        return $this->resourceDestroy($listName);
    }
}

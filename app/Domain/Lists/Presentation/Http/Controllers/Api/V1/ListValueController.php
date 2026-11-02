<?php

declare(strict_types=1);

namespace App\Domain\Lists\Presentation\Http\Controllers\Api\V1;

use App\Domain\Lists\Actions\CreateListValue;
use App\Domain\Lists\Actions\DeleteListValue;
use App\Domain\Lists\Actions\UpdateListValue;
use App\Domain\Lists\Data\CreateListValueData;
use App\Domain\Lists\Data\ListValueData;
use App\Domain\Lists\Data\UpdateListValueData;
use App\Domain\Lists\Models\ListName;
use App\Domain\Lists\Models\ListValue;
use App\Domain\Lists\Presentation\Http\Requests\CreateListValueRequest;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ListValueController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'name',
        'parent_id',
        'is_active',
        'is_system',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * List values for a list name.
     *
     * Supports Ransack `q[...]` filtering — e.g. `?q[is_active_eq]=true` to
     * return only active values — and `sort` on the whitelisted fields.
     *
     * @response array{list_values: list<ListValueData>}
     */
    public function index(Request $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.view', 'static-data:read');

        $query = $listName->values()->getQuery();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $this->hasExplicitSort($request)) {
            $query->orderBy('sort_order');
        }

        $values = $query->get()
            ->map(fn (ListValue $value): array => ListValueData::fromModel($value)->toArray())
            ->all();

        return $this->respondWithCollection($values, 'list_values');
    }

    /**
     * Create a value for a list name.
     */
    #[ApiResponse(status: 201, type: 'array{list_value: ListValueData}')]
    public function store(CreateListValueRequest $request, ListName $listName): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');

        $dto = CreateListValueData::from([
            ...$request->validated(),
            'list_name_id' => $listName->id,
        ]);

        $result = (new CreateListValue)($dto);

        return $this->respondWith(
            $result->toArray(),
            'list_value',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a list value.
     *
     * @response array{list_value: ListValueData}
     */
    public function update(Request $request, ListName $listName, ListValue $listValue): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');
        $listValue = $this->ownedValue($listName, $listValue);

        $validated = $request->validate(UpdateListValueData::rules());
        $dto = UpdateListValueData::from($validated);

        $result = (new UpdateListValue)($listValue, $dto);

        return $this->respondWith(
            $result->toArray(),
            'list_value',
        );
    }

    /**
     * Delete a list value.
     */
    public function destroy(ListName $listName, ListValue $listValue): JsonResponse
    {
        $this->authorizeApi('list-values.manage', 'static-data:write');
        $listValue = $this->ownedValue($listName, $listValue);

        (new DeleteListValue)($listValue);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ownedValue(ListName $listName, ListValue $listValue): ListValue
    {
        return $listName->values()->findOrFail($listValue->getKey());
    }
}

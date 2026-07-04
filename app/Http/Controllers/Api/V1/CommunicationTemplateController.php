<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\DeleteCommunicationTemplate;
use App\Actions\Notifications\RestoreCommunicationTemplateVersion;
use App\Actions\Notifications\SaveCommunicationTemplate;
use App\Data\Notifications\CommunicationTemplateData;
use App\Data\Notifications\CommunicationTemplateVersionData;
use App\Data\Notifications\SaveCommunicationTemplateData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CommunicationTemplateController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'notification_type_id',
        'store_id',
        'name',
        'slug',
        'channel',
        'is_default',
        'is_system',
        'is_active',
        'version',
        'last_edited_at',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected array $filterAliases = [
        'active' => 'is_active',
        'default' => 'is_default',
        'system' => 'is_system',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'slug',
        'channel',
        'version',
        'last_edited_at',
        'created_at',
        'updated_at',
    ];

    /**
     * List communication templates with Ransack filtering and pagination.
     *
     * @response array{communication_templates: list<CommunicationTemplateData>, meta: array{total: int, per_page: int, page: int}}
     */
    #[ApiResponse(200, 'Paginated communication template collection', type: 'array{communication_templates: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    #[PriceWeight(0)]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:read');

        $query = CommunicationTemplate::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->orderBy('name');
        }

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (CommunicationTemplate $template): array => CommunicationTemplateData::fromModel($template)->toArray())
            ->all();

        return $this->respondWithCollection($items, 'communication_templates', $paginator);
    }

    /**
     * Show a communication template.
     *
     * @response array{communication_template: CommunicationTemplateData}
     */
    #[ApiResponse(200, 'Communication template resource', type: 'array{communication_template: array<string, mixed>}')]
    #[PriceWeight(0)]
    public function show(CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:read');
        Gate::authorize('view', $communicationTemplate);

        return $this->respondWith(
            CommunicationTemplateData::fromModel($communicationTemplate)->toArray(),
            'communication_template',
        );
    }

    /**
     * Create a custom communication template.
     *
     * @response 201 array{communication_template: CommunicationTemplateData}
     */
    #[ApiResponse(201, 'Created communication template', type: 'array{communication_template: array<string, mixed>}')]
    #[PriceWeight(1)]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:write');

        $validated = $request->validate([
            ...SaveCommunicationTemplateData::rules(),
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);
        $dto = SaveCommunicationTemplateData::from($validated);

        $this->assertUserCanAccessStore($dto->store_id);

        $result = (new SaveCommunicationTemplate)(null, $dto);

        return $this->respondWith(
            $result->toArray(),
            'communication_template',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a communication template and snapshot the previous version.
     *
     * @response array{communication_template: CommunicationTemplateData}
     */
    #[ApiResponse(200, 'Updated communication template', type: 'array{communication_template: array<string, mixed>}')]
    #[PriceWeight(1)]
    public function update(Request $request, CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:write');

        $validated = $request->validate(SaveCommunicationTemplateData::rules());
        $dto = SaveCommunicationTemplateData::from([
            ...$validated,
            'store_id' => $validated['store_id'] ?? $communicationTemplate->store_id,
            'notification_type_id' => $validated['notification_type_id'] ?? $communicationTemplate->notification_type_id,
        ]);

        if ($dto->store_id !== null && $dto->store_id !== $communicationTemplate->store_id) {
            $this->assertUserCanAccessStore($dto->store_id);
        }

        $result = (new SaveCommunicationTemplate)($communicationTemplate, $dto);

        return $this->respondWith(
            $result->toArray(),
            'communication_template',
        );
    }

    /**
     * Soft delete a communication template.
     *
     * System templates cannot be deleted and return 422.
     */
    #[ApiResponse(204, 'Communication template deleted')]
    #[ApiResponse(422, 'System template delete blocked', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[PriceWeight(1)]
    public function destroy(CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:write');

        (new DeleteCommunicationTemplate)($communicationTemplate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * List version snapshots for a communication template.
     *
     * @response array{communication_template_versions: list<CommunicationTemplateVersionData>}
     */
    #[ApiResponse(200, 'Communication template version history', type: 'array{communication_template_versions: list<array<string, mixed>>}')]
    #[PriceWeight(0)]
    public function versions(CommunicationTemplate $communicationTemplate): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:read');
        Gate::authorize('view', $communicationTemplate);

        $versions = $communicationTemplate->versions()
            ->orderByDesc('version')
            ->get()
            ->map(fn (CommunicationTemplateVersion $version): array => CommunicationTemplateVersionData::fromModel($version)->toArray())
            ->all();

        return response()->json([
            'communication_template_versions' => $versions,
        ]);
    }

    /**
     * Restore template content from a historical version snapshot.
     *
     * @response array{communication_template: CommunicationTemplateData}
     */
    #[ApiResponse(200, 'Restored communication template', type: 'array{communication_template: array<string, mixed>}')]
    #[PriceWeight(1)]
    public function restoreVersion(CommunicationTemplate $communicationTemplate, CommunicationTemplateVersion $version): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communication-templates:write');

        $result = (new RestoreCommunicationTemplateVersion)($communicationTemplate, $version);

        return $this->respondWith(
            $result->toArray(),
            'communication_template',
        );
    }

    private function assertUserCanAccessStore(int $storeId): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return;
        }

        if (! in_array($storeId, $storeIds, true)) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}

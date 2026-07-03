<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\CreateDocumentTemplate;
use App\Actions\Documents\DeleteDocumentTemplate;
use App\Actions\Documents\DuplicateDocumentTemplate;
use App\Actions\Documents\ExportDocumentTemplate;
use App\Actions\Documents\ImportDocumentTemplate;
use App\Actions\Documents\RestoreDefaultDocumentTemplate;
use App\Actions\Documents\RestoreDocumentTemplateVersion;
use App\Actions\Documents\SetDefaultDocumentTemplate;
use App\Actions\Documents\UpdateDocumentTemplate;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Documents\DocumentTemplateData;
use App\Data\Documents\ImportDocumentTemplateData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DocumentTemplateController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'store_id',
        'document_type',
        'is_active',
        'is_default',
        'name',
        'slug',
    ];

    /** @var array<string, string> */
    protected array $filterAliases = [
        'type' => 'document_type',
        'active' => 'is_active',
        'default' => 'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'name',
        'document_type',
        'version',
        'last_edited_at',
        'created_at',
        'updated_at',
    ];

    /**
     * List document templates for the current store scope.
     *
     * Supports Ransack filters on type, active, and default status.
     *
     * @response array{document_templates: list<DocumentTemplateData>, meta: array{total: int, per_page: int, page: int}}
     */
    #[PriceWeight(0)]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('document_templates.view', 'documents:read');

        $query = DocumentTemplate::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (DocumentTemplate $template): array => DocumentTemplateData::fromModel($template)->toArray())
            ->all();

        return response()->json([
            'document_templates' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Show a document template with nested version history.
     *
     * @response array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(0)]
    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.view', 'documents:read');
        Gate::authorize('view', $documentTemplate);

        return $this->respondWith(
            DocumentTemplateData::fromModel($documentTemplate, includeVersions: true)->toArray(),
            'document_template',
        );
    }

    /**
     * Create a custom document template for a store.
     *
     * @response 201 array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $validated = $request->validate(CreateDocumentTemplateData::rules());
        $dto = CreateDocumentTemplateData::from($validated);

        $result = (new CreateDocumentTemplate)($dto);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a document template and snapshot the previous version.
     *
     * @response array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $validated = $request->validate(UpdateDocumentTemplateData::rules());
        $dto = UpdateDocumentTemplateData::from($validated);

        $result = (new UpdateDocumentTemplate)($documentTemplate, $dto);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
        );
    }

    /**
     * Soft delete a document template.
     *
     * System templates cannot be deleted and return 422.
     */
    #[ApiResponse(204, 'Template deleted')]
    #[ApiResponse(422, 'System template delete blocked', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[PriceWeight(1)]
    public function destroy(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        (new DeleteDocumentTemplate)($documentTemplate);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Mark a template as the default for its store and document type.
     *
     * @response array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function setDefault(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $result = (new SetDefaultDocumentTemplate)($documentTemplate);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
        );
    }

    /**
     * Duplicate a template as a new non-system copy.
     *
     * @response 201 array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function duplicate(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $result = (new DuplicateDocumentTemplate)($documentTemplate);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Restore a system template to its shipped default content.
     *
     * @response array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function restoreDefault(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $result = (new RestoreDefaultDocumentTemplate)($documentTemplate);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
        );
    }

    /**
     * Restore template content from a historical version snapshot.
     *
     * @response array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function restoreVersion(DocumentTemplate $documentTemplate, DocumentTemplateVersion $version): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $result = (new RestoreDocumentTemplateVersion)($documentTemplate, $version);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
        );
    }

    /**
     * Export a template definition as portable JSON (no version history).
     *
     * @response array{document_template_export: array<string, mixed>}
     */
    #[PriceWeight(0)]
    public function export(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeApi('document_templates.view', 'documents:read');
        Gate::authorize('view', $documentTemplate);

        return response()->json([
            'document_template_export' => (new ExportDocumentTemplate)($documentTemplate),
        ]);
    }

    /**
     * Import a portable JSON template export as a new non-system template.
     *
     * @response 201 array{document_template: DocumentTemplateData}
     */
    #[PriceWeight(1)]
    public function import(Request $request): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $validated = $request->validate(ImportDocumentTemplateData::rules());
        $dto = ImportDocumentTemplateData::from($validated);

        $result = (new ImportDocumentTemplate)($dto);

        return $this->respondWith(
            $result->toArray(),
            'document_template',
            Response::HTTP_CREATED,
        );
    }
}

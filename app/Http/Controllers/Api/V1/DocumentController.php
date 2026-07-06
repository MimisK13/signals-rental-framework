<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\ConfigureDocumentPublicLink;
use App\Actions\Documents\CreateDocumentPublicLink;
use App\Actions\Documents\FinaliseDocument;
use App\Actions\Documents\GenerateDocument;
use App\Actions\Documents\RevokeDocumentPublicLink;
use App\Actions\Documents\VoidDocument;
use App\Data\Documents\BatchGenerateDocumentsData;
use App\Data\Documents\ConfigureDocumentPublicLinkData;
use App\Data\Documents\CreateDocumentPublicLinkData;
use App\Data\Documents\DocumentData;
use App\Data\Documents\GenerateDocumentData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Jobs\BatchGenerateDocumentsJob;
use App\Jobs\GenerateDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'store_id',
        'document_type',
        'status',
        'documentable_type',
        'documentable_id',
        'document_number',
        'created_at',
    ];

    /** @var array<string, string> */
    protected array $filterAliases = [
        'type' => 'document_type',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'document_number',
        'document_type',
        'status',
        'created_at',
        'updated_at',
        'first_viewed_at',
    ];

    /**
     * List generated documents with Ransack filters and store scoping.
     *
     * @response array{documents: list<DocumentData>, meta: array{total: int, per_page: int, page: int}}
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('documents.view', 'documents:read');

        $query = Document::query()->with('documentTemplate');
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->latest('created_at');
        }

        $paginator = $this->paginateQuery($query, $request);

        $documents = $paginator->getCollection()
            ->map(fn (Document $document): array => DocumentData::fromModel(
                $document,
                includeDownloadUrl: Gate::allows('view', $document),
            )->toArray())
            ->all();

        return $this->respondWithCollection($documents, 'documents', $paginator);
    }

    /**
     * Show a generated document with template and public-link metadata.
     *
     * @response array{document: DocumentData}
     */
    public function show(Document $document): JsonResponse
    {
        $this->authorizeApi('documents.view', 'documents:read');
        Gate::authorize('view', $document);

        return $this->respondWith(
            DocumentData::fromModel(
                $document->loadMissing('documentTemplate'),
                includeTemplate: true,
                includePublicLink: Gate::allows('share', $document),
                includeDownloadUrl: true,
            )->toArray(),
            'document',
        );
    }

    /**
     * Generate a document from a documentable source.
     *
     * Pass async=true to queue generation on the exports queue (202 + job_id).
     *
     * @response 201 array{document: DocumentData}
     * @response 202 array{message: string, job_id: string}
     */
    public function generate(Request $request): JsonResponse
    {
        $this->authorizeApi('documents.create', 'documents:write');

        $validated = $request->validate(GenerateDocumentData::rules());
        $dto = GenerateDocumentData::from($validated);

        if ($request->boolean('async')) {
            /** @var User $user */
            $user = $request->user();
            $jobId = (string) Str::uuid();

            GenerateDocumentJob::dispatch($jobId, $dto, $user->id);

            return $this->respondAccepted($jobId);
        }

        $result = app(GenerateDocument::class)($dto);

        return $this->respondWith(
            $result->toArray(),
            'document',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Batch-generate documents asynchronously.
     *
     * @response 202 array{message: string, job_id: string}
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        $this->authorizeApi('documents.create', 'documents:write');

        $validated = $request->validate(BatchGenerateDocumentsData::rules());
        $dto = BatchGenerateDocumentsData::from($validated);
        /** @var User $user */
        $user = $request->user();
        $jobId = (string) Str::uuid();

        BatchGenerateDocumentsJob::dispatch($jobId, $dto->toGenerateRequests(), $user->id);

        return $this->respondAccepted($jobId);
    }

    /**
     * Finalise a draft document, locking it from further changes.
     *
     * @response array{document: DocumentData}
     */
    public function finalise(Document $document): JsonResponse
    {
        $this->authorizeApi('documents.finalise', 'documents:write');

        $result = app(FinaliseDocument::class)($document);

        return $this->respondWith($result->toArray(), 'document');
    }

    /**
     * Void a finalised document.
     *
     * @response array{document: DocumentData}
     */
    public function void(Document $document): JsonResponse
    {
        $this->authorizeApi('documents.delete', 'documents:write');

        $result = app(VoidDocument::class)($document);

        return $this->respondWith($result->toArray(), 'document');
    }

    /**
     * Create or rotate a public sharing link for a document.
     *
     * @response array{document: DocumentData}
     */
    public function createPublicLink(Request $request, Document $document): JsonResponse
    {
        $this->authorizeApi('documents.share', 'documents:write');

        $validated = $request->validate(CreateDocumentPublicLinkData::rules());
        $result = app(CreateDocumentPublicLink::class)($document, CreateDocumentPublicLinkData::from($validated));

        return $this->respondWith($result->toArray(), 'document');
    }

    /**
     * Update expiry or PIN settings for an existing public link.
     *
     * @response array{document: DocumentData}
     */
    public function configurePublicLink(Request $request, Document $document): JsonResponse
    {
        $this->authorizeApi('documents.share', 'documents:write');

        $validated = $request->validate(ConfigureDocumentPublicLinkData::rules());
        $result = app(ConfigureDocumentPublicLink::class)($document, ConfigureDocumentPublicLinkData::from($validated));

        return $this->respondWith($result->toArray(), 'document');
    }

    /**
     * Revoke the public sharing link for a document.
     *
     * @response array{document: DocumentData}
     */
    public function revokePublicLink(Document $document): JsonResponse
    {
        $this->authorizeApi('documents.share', 'documents:write');

        $result = app(RevokeDocumentPublicLink::class)($document);

        return $this->respondWith($result->toArray(), 'document');
    }
}

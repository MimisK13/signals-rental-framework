<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\Documents\DocumentResolverRegistry;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class DocumentTypeController extends Controller
{
    /**
     * List registered document types and their resolver metadata.
     */
    #[PriceWeight(0)]
    public function index(DocumentResolverRegistry $registry): JsonResponse
    {
        $this->authorizeApi('documents.view', 'documents:read');

        return $this->respondWithCollection($registry->types(), 'document_types');
    }

    /**
     * Return the grouped merge-field tree for a document type.
     */
    #[ApiResponse(200, 'Grouped document merge fields', type: 'array{document_type: string, groups: list<array{name: string, fields: list<array<string, mixed>>}>}')]
    #[ApiResponse(404, 'Unknown document type', type: 'array{message: string}')]
    #[PriceWeight(0)]
    public function fields(string $type, DocumentResolverRegistry $registry): JsonResponse
    {
        $this->authorizeApi('schema.read', 'schema:read');

        try {
            $groups = $registry->fieldTree($type);
        } catch (InvalidArgumentException) {
            return $this->respondWithError("Unknown document type: {$type}", Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'document_type' => $type,
            'groups' => $groups,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Documents\UpdateDocumentSequence;
use App\Data\Documents\DocumentSequenceData;
use App\Data\Documents\UpdateDocumentSequenceData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\DocumentSequence;
use App\Support\Api\Attributes\PriceWeight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSequenceController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'store_id',
        'document_type',
        'reset_frequency',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'document_type',
        'created_at',
        'updated_at',
    ];

    /**
     * List document numbering sequences for the current store scope.
     */
    #[PriceWeight(0)]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('document_templates.view', 'documents:read');

        $query = DocumentSequence::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (DocumentSequence $sequence): array => DocumentSequenceData::fromModel($sequence)->toArray())
            ->all();

        return response()->json([
            'document_sequences' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * Update numbering configuration for a document sequence.
     */
    #[PriceWeight(1)]
    public function update(Request $request, DocumentSequence $documentSequence): JsonResponse
    {
        $this->authorizeApi('document_templates.edit', 'documents:write');

        $validated = $request->validate(UpdateDocumentSequenceData::rules());
        $dto = UpdateDocumentSequenceData::from($validated);

        $result = (new UpdateDocumentSequence)($documentSequence, $dto);

        return $this->respondWith($result->toArray(), 'document_sequence');
    }
}

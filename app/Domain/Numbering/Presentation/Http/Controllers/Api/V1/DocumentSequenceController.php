<?php

declare(strict_types=1);

namespace App\Domain\Numbering\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Support\Attributes\PriceWeight;
use App\Domain\Numbering\Actions\UpdateDocumentSequence;
use App\Domain\Numbering\Data\DocumentSequenceData;
use App\Domain\Numbering\Data\UpdateDocumentSequenceData;
use App\Domain\Numbering\Models\DocumentSequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DocumentSequenceController extends Controller
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
        $this->authorizeApi('numbering.view', 'documents:read');

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
        $this->authorizeApi('numbering.edit', 'documents:write');

        $validated = $request->validate(UpdateDocumentSequenceData::rules());
        $dto = UpdateDocumentSequenceData::from($validated);

        $result = (new UpdateDocumentSequence)($documentSequence, $dto);

        return $this->respondWith($result->toArray(), 'document_sequence');
    }
}

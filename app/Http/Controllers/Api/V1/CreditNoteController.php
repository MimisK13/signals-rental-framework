<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\IssueCreditNote;
use App\Data\Invoices\CreditNoteData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\CreditNote;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use LogicException;

class CreditNoteController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'number',
        'status',
        'invoice_id',
        'store_id',
        'currency_code',
        'issued_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'number',
        'status',
        'total',
        'issued_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'invoice',
        'store',
        'items',
        'taxes',
    ];

    protected function modelClass(): string
    {
        return CreditNote::class;
    }

    protected function responseDataClass(): string
    {
        return CreditNoteData::class;
    }

    protected function createDataClass(): string
    {
        return IssueCreditNoteData::class;
    }

    protected function updateDataClass(): string
    {
        return IssueCreditNoteData::class;
    }

    protected function createActionClass(): string
    {
        return IssueCreditNote::class;
    }

    protected function updateActionClass(): string
    {
        return IssueCreditNote::class;
    }

    protected function deleteActionClass(): string
    {
        return IssueCreditNote::class;
    }

    protected function singularKey(): string
    {
        return 'credit_note';
    }

    protected function pluralKey(): string
    {
        return 'credit_notes';
    }

    protected function entityType(): string
    {
        return 'credit_notes';
    }

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    protected function permissions(): array
    {
        return [
            'view' => 'invoices.view',
            'create' => 'invoices.credit',
            'edit' => 'invoices.credit',
            'delete' => 'invoices.credit',
        ];
    }

    /**
     * @return array{read: string, write: string}
     */
    protected function abilities(): array
    {
        return ['read' => 'credit-notes:read', 'write' => 'credit-notes:write'];
    }

    #[ApiResponse(200, 'Paginated credit note collection', type: 'array{credit_notes: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CreditNote::class);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        return $this->resourceIndex($request);
    }

    #[ApiResponse(200, 'Credit note resource', type: 'array{credit_note: array<string, mixed>}')]
    public function show(Request $request, CreditNote $creditNote): JsonResponse
    {
        Gate::authorize('view', $creditNote);
        $this->authorizeApi($this->permissions()['view'], $this->abilities()['read']);

        return $this->resourceShow($request, $creditNote);
    }

    public function store(Request $request): never
    {
        throw new LogicException('Credit notes are created via POST /api/v1/invoices/{invoice}/credit_notes.');
    }

    public function update(Request $request, CreditNote $creditNote): never
    {
        throw new LogicException('Credit notes are immutable after issue.');
    }

    public function destroy(CreditNote $creditNote): never
    {
        throw new LogicException('Credit notes cannot be deleted via the API.');
    }
}

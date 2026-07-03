<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\CreateDraftInvoice;
use App\Actions\Invoices\DeleteDraftInvoice;
use App\Actions\Invoices\IssueCreditNote;
use App\Actions\Invoices\IssueInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\RecordRefund;
use App\Actions\Invoices\UpdateDraftInvoice;
use App\Actions\Invoices\VoidInvoice;
use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Data\Invoices\CreateDraftInvoiceData;
use App\Data\Invoices\InvoiceData;
use App\Data\Invoices\IssueCreditNoteData;
use App\Data\Invoices\RecordPaymentData;
use App\Data\Invoices\UpdateDraftInvoiceData;
use App\Enums\BillingCommunicationKind;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Http\Traits\ResourceActions;
use App\Models\Document;
use App\Models\Invoice;
use App\Services\FileService;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * API surface for the invoice lifecycle.
 *
 * Reads hit the `invoices` projection directly. Every write delegates to the
 * existing invoice actions, which fire Verbs events and dual-write the projection.
 */
class InvoiceController extends Controller
{
    use FiltersQueries, ResourceActions;

    /** @var list<string> */
    protected array $allowedFilters = [
        'number',
        'type',
        'status',
        'member_id',
        'store_id',
        'opportunity_id',
        'currency_code',
        'issued_at',
        'due_at',
        'paid_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'number',
        'type',
        'status',
        'total',
        'amount_outstanding',
        'issued_at',
        'due_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedIncludes = [
        'member',
        'store',
        'opportunity',
        'items',
        'taxes',
        'payments',
        'creditNotes',
    ];

    protected function modelClass(): string
    {
        return Invoice::class;
    }

    protected function responseDataClass(): string
    {
        return InvoiceData::class;
    }

    protected function createDataClass(): string
    {
        return CreateDraftInvoiceData::class;
    }

    protected function updateDataClass(): string
    {
        return UpdateDraftInvoiceData::class;
    }

    protected function createActionClass(): string
    {
        return CreateDraftInvoice::class;
    }

    protected function updateActionClass(): string
    {
        return UpdateDraftInvoice::class;
    }

    protected function deleteActionClass(): string
    {
        return DeleteDraftInvoice::class;
    }

    protected function singularKey(): string
    {
        return 'invoice';
    }

    protected function pluralKey(): string
    {
        return 'invoices';
    }

    protected function entityType(): string
    {
        return 'invoices';
    }

    /**
     * @return array{view: string, create: string, edit: string, delete: string}
     */
    protected function permissions(): array
    {
        return [
            'view' => 'invoices.view',
            'create' => 'invoices.create',
            'edit' => 'invoices.edit',
            'delete' => 'invoices.delete',
        ];
    }

    /**
     * @return array{read: string, write: string}
     */
    protected function abilities(): array
    {
        return ['read' => 'invoices:read', 'write' => 'invoices:write'];
    }

    /**
     * List invoices with Ransack filtering, sorting, pagination, and includes.
     */
    #[ApiResponse(200, 'Paginated invoice collection', type: 'array{invoices: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    public function index(Request $request): JsonResponse
    {
        return $this->resourceIndex($request);
    }

    /**
     * Show a single invoice.
     */
    #[ApiResponse(200, 'Invoice resource', type: 'array{invoice: array<string, mixed>}')]
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        return $this->resourceShow($request, $invoice);
    }

    /**
     * Create a new draft invoice.
     */
    #[ApiResponse(201, 'Created draft invoice', type: 'array{invoice: array<string, mixed>}')]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi($this->permissions()['create'], $this->abilities()['write']);

        $dto = CreateDraftInvoiceData::validateAndCreate($request->all());
        $result = (new CreateDraftInvoice)($dto);

        return $this->respondWith(
            $result->toArray(),
            $this->singularKey(),
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update draft-only invoice header fields.
     */
    #[ApiResponse(200, 'Updated draft invoice', type: 'array{invoice: array<string, mixed>}')]
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('update', $invoice);

        $validated = $request->validate(UpdateDraftInvoiceData::rules());
        $dto = UpdateDraftInvoiceData::from([
            ...$validated,
            'notes_provided' => $request->has('notes'),
            'reference_provided' => $request->has('reference'),
        ]);

        $result = (new UpdateDraftInvoice)($invoice, $dto);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    /**
     * Soft-delete a draft invoice.
     */
    #[ApiResponse(204, 'Draft invoice deleted')]
    public function destroy(Invoice $invoice): JsonResponse
    {
        Gate::authorize('delete', $invoice);

        return $this->resourceDestroy($invoice);
    }

    /**
     * Download the issued invoice PDF via a signed redirect, or report async status.
     */
    #[ApiResponse(302, 'Redirect to signed PDF URL')]
    #[ApiResponse(202, 'PDF generation status', type: 'array{message: string, status: string, job_id?: string}')]
    public function pdf(Invoice $invoice): JsonResponse|RedirectResponse
    {
        $this->authorizeApi('invoices.view', 'invoices:read');
        Gate::authorize('view', $invoice);

        $documentType = $invoice->typeEnum()->documentType();

        $document = Document::query()
            ->where('documentable_type', $invoice->getMorphClass())
            ->where('documentable_id', $invoice->id)
            ->where('document_type', $documentType)
            ->where('status', '!=', DocumentStatus::Void)
            ->whereNotNull('pdf_path')
            ->latest('id')
            ->first();

        if ($document !== null) {
            $url = app(FileService::class)->signedUrlOrNull($document->pdf_path);

            if ($url !== null) {
                return redirect()->away($url);
            }
        }

        /** @var array{status?: string, job_id?: string}|null $status */
        $status = Cache::get(IssueInvoice::pdfStatusCacheKey((int) $invoice->id));

        if (is_array($status) && in_array($status['status'] ?? null, ['queued', 'processing'], true)) {
            return response()->json([
                'message' => 'Invoice PDF is being generated.',
                'status' => $status['status'],
                'job_id' => $status['job_id'] ?? null,
            ], Response::HTTP_ACCEPTED);
        }

        return response()->json([
            'message' => 'Invoice PDF is not available yet.',
            'status' => 'pending',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Issue a draft invoice to the customer.
     */
    #[ApiResponse(200, 'Issued invoice', type: 'array{invoice: array<string, mixed>}')]
    public function issue(Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('invoices.issue', $this->abilities()['write']);

        $result = (new IssueInvoice)($invoice);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    /**
     * Void an unpaid invoice.
     */
    #[ApiResponse(200, 'Voided invoice', type: 'array{invoice: array<string, mixed>}')]
    public function void(Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('invoices.void', $this->abilities()['write']);

        $result = (new VoidInvoice)($invoice);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    /**
     * Record a payment against an issued invoice.
     */
    #[ApiResponse(200, 'Invoice after payment', type: 'array{invoice: array<string, mixed>}')]
    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('payments.record', 'payments:write');

        $dto = RecordPaymentData::validateAndCreate($request->all());
        $result = (new RecordPayment)($invoice, $dto);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    /**
     * Record a refund against a paid or partially paid invoice.
     */
    #[ApiResponse(200, 'Invoice after refund', type: 'array{invoice: array<string, mixed>}')]
    public function recordRefund(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('payments.record', 'payments:write');

        $dto = RecordPaymentData::validateAndCreate($request->all());
        $result = (new RecordRefund)($invoice, $dto);

        return $this->respondWith($result->toArray(), $this->singularKey());
    }

    /**
     * Issue a full or partial credit note against an invoice.
     */
    #[ApiResponse(201, 'Issued credit note', type: 'array{credit_note: array<string, mixed>}')]
    public function issueCreditNote(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('invoices.credit', 'invoices:write');

        $dto = IssueCreditNoteData::validateAndCreate($request->all());
        $result = (new IssueCreditNote)($invoice, $dto);

        return $this->respondWith(
            $result->toArray(),
            'credit_note',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Queue the invoice email to the member.
     */
    #[ApiResponse(202, 'Invoice email queued', type: 'array{message: string}')]
    public function email(Invoice $invoice): JsonResponse
    {
        $this->authorizeApi('invoices.view', 'invoices:read');
        Gate::authorize('view', $invoice);

        if (in_array($invoice->status, ['draft', 'void'], true)) {
            return response()->json([
                'message' => 'Only issued invoices can be emailed.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        app(DispatchBillingCustomerCommunication::class)(
            BillingCommunicationKind::Invoice,
            (int) $invoice->id,
            (int) $invoice->store_id,
            explicit: true,
        );

        return response()->json([
            'message' => 'Invoice email has been queued.',
        ], Response::HTTP_ACCEPTED);
    }
}

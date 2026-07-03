<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\IssueInvoice;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Api\Controller;
use App\Models\Document;
use App\Models\Invoice;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /**
     * Download the issued invoice PDF via a signed redirect, or report async status.
     *
     * @response 302 Redirect to signed PDF URL
     * @response 202 array{message: string, job_id?: string, status: string}
     */
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
}

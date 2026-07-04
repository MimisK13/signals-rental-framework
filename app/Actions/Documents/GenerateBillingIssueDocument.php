<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\DocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Models\CreditNote;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Services\Documents\DocumentEventDispatcher;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Generates (or returns) the issued PDF for an invoice or credit note.
 *
 * Used by the billing issue lifecycle — idempotent per source issue, bypasses
 * the documents.create permission gate while still persisting through the
 * standard render pipeline and S3 storage.
 */
class GenerateBillingIssueDocument
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentNumberGenerator $numberGenerator,
        private readonly FileService $fileService,
        private readonly DocumentTemplateResolver $templateResolver,
    ) {}

    public function forInvoice(Invoice $invoice, ?int $generatedByUserId = null): DocumentData
    {
        $documentType = $this->documentTypeForInvoice($invoice);

        return $this->generate(
            documentType: $documentType,
            resolverType: 'invoice',
            source: $invoice,
            storeId: (int) $invoice->store_id,
            generatedByUserId: $generatedByUserId,
        );
    }

    public function forCreditNote(CreditNote $creditNote, ?int $generatedByUserId = null): DocumentData
    {
        $creditNote->loadMissing('invoice');

        return $this->generate(
            documentType: 'credit_note',
            resolverType: 'credit_note',
            source: $creditNote,
            storeId: (int) $creditNote->invoice->store_id,
            generatedByUserId: $generatedByUserId,
        );
    }

    public function forReceipt(InvoicePayment $payment, ?int $generatedByUserId = null): DocumentData
    {
        $payment->loadMissing('invoice');

        return $this->generate(
            documentType: 'receipt',
            resolverType: 'receipt',
            source: $payment,
            storeId: (int) $payment->invoice->store_id,
            generatedByUserId: $generatedByUserId,
        );
    }

    public function forStatement(Member $member, int $storeId, ?int $generatedByUserId = null): DocumentData
    {
        return $this->generate(
            documentType: 'statement',
            resolverType: 'statement',
            source: $member,
            storeId: $storeId,
            generatedByUserId: $generatedByUserId,
            allowExisting: false,
        );
    }

    private function documentTypeForInvoice(Invoice $invoice): string
    {
        return $invoice->typeEnum()->documentType();
    }

    private function generate(
        string $documentType,
        string $resolverType,
        Model $source,
        int $storeId,
        ?int $generatedByUserId,
        bool $allowExisting = true,
    ): DocumentData {
        if ($allowExisting) {
            $existing = Document::query()
                ->where('documentable_type', $source->getMorphClass())
                ->where('documentable_id', $source->getKey())
                ->where('document_type', $documentType)
                ->where('status', '!=', DocumentStatus::Void)
                ->whereNotNull('pdf_path')
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return DocumentData::fromModel($existing, includeDownloadUrl: true);
            }
        }

        $template = $this->templateResolver->resolveForGeneration(
            $storeId,
            null,
            $documentType,
        );

        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'document_type' => ['No active template exists for this document type and store.'],
            ]);
        }

        $renderResult = $this->renderer->render(
            $template,
            $resolverType,
            (int) $source->getKey(),
            DocumentOutputFormat::Pdf,
        );

        if (! $renderResult->success || $renderResult->renderedData === null || $renderResult->pdfBinary === null) {
            throw ValidationException::withMessages([
                'document_type' => [$renderResult->error ?? 'Unable to render billing document PDF.'],
            ]);
        }

        $documentNumber = $this->numberGenerator->next($template->store_id, $documentType);

        $document = Document::query()->create([
            'store_id' => $template->store_id,
            'document_template_id' => $template->id,
            'generated_by_user_id' => $generatedByUserId,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'documentable_type' => $source->getMorphClass(),
            'documentable_id' => $source->getKey(),
            'status' => DocumentStatus::Final,
            'rendered_data' => $renderResult->renderedData,
            'locale' => (string) config('documents.defaults.locale', 'en'),
            'currency_code' => $this->resolveCurrencyCode($source),
            'template_version' => $template->version,
        ]);

        $pdfPath = $this->fileService->storeBinary(
            $renderResult->pdfBinary,
            'documents',
            'pdf',
            $document,
        );

        $document->update(['pdf_path' => $pdfPath]);
        $document->refresh();

        app(DocumentEventDispatcher::class)->document($document, 'document.created');
        app(DocumentEventDispatcher::class)->pdfGenerated($document);

        return DocumentData::fromModel($document, includeDownloadUrl: true);
    }

    private function resolveCurrencyCode(Model $source): string
    {
        if ($source instanceof Invoice) {
            return $source->currency_code !== ''
                ? $source->currency_code
                : (string) settings('company.base_currency', 'GBP');
        }

        if ($source instanceof CreditNote) {
            return $source->currency_code !== ''
                ? $source->currency_code
                : (string) settings('company.base_currency', 'GBP');
        }

        if ($source instanceof InvoicePayment) {
            $source->loadMissing('invoice');

            return $source->invoice->currency_code !== ''
                ? $source->invoice->currency_code
                : (string) settings('company.base_currency', 'GBP');
        }

        if ($source instanceof Member) {
            return $source->default_currency_code !== null && $source->default_currency_code !== ''
                ? $source->default_currency_code
                : (string) settings('company.base_currency', 'GBP');
        }

        return (string) settings('company.base_currency', 'GBP');
    }
}

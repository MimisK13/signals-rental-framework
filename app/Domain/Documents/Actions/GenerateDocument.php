<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Contracts\DocumentRenderer;
use App\Domain\Documents\Data\DocumentData;
use App\Domain\Documents\Data\GenerateDocumentData;
use App\Domain\Documents\Enums\DocumentOutputFormat;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use App\Domain\Documents\Services\DocumentNumberGenerator;
use App\Domain\Documents\Services\DocumentTemplateResolver;
use App\Domain\Invoices\Models\InvoicePayment;
use App\Domain\Quotations\Models\Quotation;
use App\Models\Member;
use App\Models\Opportunity;
use App\Services\FileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GenerateDocument
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly DocumentNumberGenerator $numberGenerator,
        private readonly FileService $fileService,
        private readonly DocumentTemplateResolver $templateResolver,
    ) {}

    public function __invoke(GenerateDocumentData $data): DocumentData
    {
        Gate::authorize('create', Document::class);

        $source = $this->loadSource($data);
        $storeId = $this->resolveStoreId($data, $source);
        $template = $this->templateResolver->resolveForGeneration(
            $storeId,
            $data->document_template_id,
            $data->document_type,
        );

        $this->assertTemplateAccessible($template);
        $documentType = $template->document_type;
        $resolverType = $template->resolverType();
        $outputFormat = $data->output_format;

        $renderResult = $this->renderer->render(
            $template,
            $resolverType,
            $source->getKey(),
            $outputFormat,
        );

        if (! $renderResult->success || $renderResult->renderedData === null) {
            throw ValidationException::withMessages([
                'document_template_id' => [$renderResult->error ?? 'Unable to render document template.'],
            ]);
        }

        if ($outputFormat === DocumentOutputFormat::Pdf && $renderResult->pdfBinary === null) {
            throw ValidationException::withMessages([
                'output_format' => ['Unable to render PDF output for this template.'],
            ]);
        }

        if ($outputFormat === DocumentOutputFormat::HtmlDownload && ($renderResult->html === null || $renderResult->html === '')) {
            throw ValidationException::withMessages([
                'output_format' => ['Unable to render HTML output for this template.'],
            ]);
        }

        $documentNumber = $this->numberGenerator->next($template->store_id, $documentType);

        $document = Document::query()->create([
            'store_id' => $template->store_id,
            'document_template_id' => $template->id,
            'generated_by_user_id' => auth()->id(),
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'documentable_type' => $source->getMorphClass(),
            'documentable_id' => $source->getKey(),
            'status' => DocumentStatus::Draft,
            'rendered_data' => $renderResult->renderedData,
            'locale' => (string) config('documents.defaults.locale', 'en'),
            'currency_code' => $this->resolveCurrencyCode($source),
            'template_version' => $template->version,
        ]);

        if ($outputFormat === DocumentOutputFormat::Pdf) {
            $pdfPath = $this->fileService->storeBinary(
                $renderResult->pdfBinary,
                'documents',
                'pdf',
                $document,
            );

            $document->update(['pdf_path' => $pdfPath]);
            $document->refresh();

            app(DocumentEventDispatcher::class)->pdfGenerated($document);
        }

        if ($outputFormat === DocumentOutputFormat::HtmlDownload) {
            $htmlPath = $this->fileService->storeBinary(
                $renderResult->html,
                'documents',
                'html',
                $document,
            );

            $document->update(['html_snapshot_path' => $htmlPath]);
        }

        $document->refresh();
        app(DocumentEventDispatcher::class)->document($document, 'document.created');

        return DocumentData::fromModel($document, includeDownloadUrl: true);
    }

    private function resolveStoreId(GenerateDocumentData $data, Model $source): int
    {
        if ($source instanceof Opportunity || $source instanceof Quotation) {
            return (int) $source->store_id;
        }

        if ($source instanceof InvoicePayment) {
            return (int) $source->invoice->store_id;
        }

        if ($source instanceof Member) {
            if ($data->document_template_id === null) {
                throw ValidationException::withMessages([
                    'document_template_id' => ['A document template is required when generating member documents.'],
                ]);
            }

            return (int) DocumentTemplate::query()->findOrFail($data->document_template_id)->store_id;
        }

        throw ValidationException::withMessages([
            'source_id' => ['Unsupported document source type.'],
        ]);
    }

    private function assertTemplateAccessible(DocumentTemplate $template): void
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'document_template_id' => ['The selected template is not active.'],
            ]);
        }
    }

    private function loadSource(GenerateDocumentData $data): Model
    {
        $modelClass = $data->resolvedDocumentableClass();

        /** @var Opportunity|Quotation|Member|InvoicePayment $source */
        $source = $modelClass::query()->findOrFail($data->resolvedSourceId());

        if ($source instanceof InvoicePayment) {
            $source->load(['invoice.member', 'invoice.store']);
            abort_if(Gate::denies('view', $source->invoice), 404);

            return $source;
        }

        abort_if(Gate::denies('view', $source), 404);

        return $source;
    }

    private function resolveCurrencyCode(Model $source): string
    {
        if ($source instanceof Opportunity || $source instanceof Quotation) {
            $code = $source->currency_code;

            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        if ($source instanceof Member) {
            $code = $source->default_currency_code;

            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        if ($source instanceof InvoicePayment) {
            $code = $source->invoice->currency_code;

            if ($code !== '') {
                return $code;
            }
        }

        return (string) settings('company.base_currency', 'GBP');
    }
}

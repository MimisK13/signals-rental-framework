<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\DocumentData;
use App\Data\Documents\GenerateDocumentData;
use App\Enums\DocumentOutputFormat;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use App\Services\Documents\DocumentNumberGenerator;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\FileService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
        $template = $this->templateResolver->resolveForGeneration(
            $source->store_id,
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

        return DocumentData::fromModel($document->refresh(), includeDownloadUrl: true);
    }

    private function assertTemplateAccessible(DocumentTemplate $template): void
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'document_template_id' => ['The selected template is not active.'],
            ]);
        }
    }

    private function loadSource(GenerateDocumentData $data): Opportunity
    {
        $modelClass = $data->resolvedDocumentableClass();

        if ($modelClass !== Opportunity::class) {
            throw new RuntimeException('Unsupported documentable model type.');
        }

        /** @var Opportunity $opportunity */
        $opportunity = $modelClass::query()->findOrFail($data->resolvedSourceId());

        Gate::authorize('view', $opportunity);

        return $opportunity;
    }

    private function resolveCurrencyCode(Opportunity $opportunity): string
    {
        $code = $opportunity->currency_code;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        return (string) settings('company.base_currency', 'GBP');
    }
}

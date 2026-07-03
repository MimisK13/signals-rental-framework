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
    ) {}

    public function __invoke(GenerateDocumentData $data): DocumentData
    {
        Gate::authorize('create', Document::class);

        $template = DocumentTemplate::query()
            ->with('store')
            ->findOrFail($data->document_template_id);

        $this->assertTemplateAccessible($template);

        $source = $this->loadSource($template, $data->source_id);
        $documentType = $template->document_type;
        $resolverType = $template->resolverType();

        $renderResult = $this->renderer->render(
            $template,
            $resolverType,
            $data->source_id,
            DocumentOutputFormat::Pdf,
        );

        if (! $renderResult->success || $renderResult->pdfBinary === null || $renderResult->renderedData === null) {
            throw ValidationException::withMessages([
                'document_template_id' => [$renderResult->error ?? 'Unable to render document template.'],
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

        $pdfPath = $this->fileService->storeBinary(
            $renderResult->pdfBinary,
            'documents',
            'pdf',
            $document,
        );

        $document->update(['pdf_path' => $pdfPath]);

        return DocumentData::fromModel($document->refresh());
    }

    private function assertTemplateAccessible(DocumentTemplate $template): void
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'document_template_id' => ['The selected template is not active.'],
            ]);
        }
    }

    private function loadSource(DocumentTemplate $template, int $sourceId): Opportunity
    {
        if ($template->model_type !== Opportunity::class) {
            throw new RuntimeException('Unsupported document template model type.');
        }

        $opportunity = Opportunity::query()->findOrFail($sourceId);

        Gate::authorize('view', $opportunity);

        if ($opportunity->store_id !== $template->store_id) {
            throw ValidationException::withMessages([
                'source_id' => ['The source record does not belong to the template store.'],
            ]);
        }

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

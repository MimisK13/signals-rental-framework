<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\DocumentRenderer;
use App\Data\Documents\DocumentPreviewData;
use App\Data\Documents\RenderDocumentPreviewData;
use App\Enums\DocumentOutputFormat;
use App\Models\DocumentTemplate;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RenderDocumentPreview
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
    ) {}

    public function __invoke(RenderDocumentPreviewData $data): DocumentPreviewData
    {
        $template = DocumentTemplate::query()
            ->with('store')
            ->findOrFail($data->document_template_id);

        Gate::authorize('view', $template);

        $renderResult = $this->renderer->render(
            $template,
            $template->resolverType(),
            $data->source_id,
            DocumentOutputFormat::Preview,
        );

        if (! $renderResult->success || $renderResult->html === null) {
            throw ValidationException::withMessages([
                'document_template_id' => [$renderResult->error ?? 'Unable to render document preview.'],
            ]);
        }

        return new DocumentPreviewData(
            html: $renderResult->html,
            rendered_data: $renderResult->renderedData,
        );
    }

    private function assertSourceAccessible(DocumentTemplate $template, int $sourceId): void
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
    }
}

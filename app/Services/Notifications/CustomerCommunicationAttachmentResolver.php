<?php

namespace App\Services\Notifications;

use App\Contracts\Documents\DocumentRenderer;
use App\Enums\DocumentOutputFormat;
use App\Models\CommunicationTemplate;
use App\Models\Document;

/**
 * Resolves PDF attachments for customer communications from frozen document snapshots (P27).
 */
class CustomerCommunicationAttachmentResolver
{
    public function __construct(
        private readonly DocumentRenderer $documentRenderer,
    ) {}

    /**
     * @return array{content: string, filename: string}|null
     */
    public function resolve(?Document $document, CommunicationTemplate $template): ?array
    {
        if ($document === null) {
            return null;
        }

        if (! $this->isAllowedByTemplateConfig($document, $template)) {
            return null;
        }

        $document->loadMissing('documentTemplate.store');

        $documentTemplate = $document->documentTemplate;

        if ($documentTemplate === null) {
            return null;
        }

        /** @var array<string, mixed>|null $renderedData */
        $renderedData = $document->rendered_data;

        if ($renderedData === null) {
            return null;
        }

        $result = $this->documentRenderer->renderFromSnapshot(
            $documentTemplate,
            $renderedData,
            DocumentOutputFormat::Pdf,
        );

        if (! $result->success || $result->pdfBinary === null || $result->pdfBinary === '') {
            return null;
        }

        $filename = trim($document->document_number).'.pdf';

        return [
            'content' => $result->pdfBinary,
            'filename' => $filename !== '.pdf' ? $filename : 'attachment.pdf',
        ];
    }

    private function isAllowedByTemplateConfig(Document $document, CommunicationTemplate $template): bool
    {
        /** @var array<string, mixed>|null $config */
        $config = $template->attachments_config;

        if ($config === null || $config === []) {
            return true;
        }

        $allowedTemplateIds = $config['document_template_ids'] ?? $config['document_templates'] ?? [];

        if (! is_array($allowedTemplateIds) || $allowedTemplateIds === []) {
            return true;
        }

        $allowedTemplateIds = array_map(intval(...), $allowedTemplateIds);

        return in_array((int) $document->document_template_id, $allowedTemplateIds, true);
    }
}

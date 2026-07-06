<?php

namespace App\Actions\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Gate;

/**
 * Export a template definition to portable JSON (content + settings; no version history).
 */
class ExportDocumentTemplate
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(DocumentTemplate $template): array
    {
        Gate::authorize('view', $template);

        return [
            'export_format' => 'signals.document_template',
            'export_version' => 1,
            'name' => $template->name,
            'description' => $template->description,
            'document_type' => $template->document_type,
            'model_type' => $template->model_type,
            'body_html' => $template->body_html,
            'header_html' => $template->header_html,
            'footer_html' => $template->footer_html,
            'styles_css' => $template->styles_css,
            'page_config' => $template->page_config,
            'branding_config' => $template->branding_config,
            'is_active' => $template->is_active,
        ];
    }
}

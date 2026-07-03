<?php

namespace App\Actions\Documents;

use App\Actions\Documents\Concerns\SnapshotsDocumentTemplateVersions;
use App\Data\Documents\DocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreDocumentTemplateVersion
{
    use SnapshotsDocumentTemplateVersions;

    public function __invoke(DocumentTemplate $template, DocumentTemplateVersion $version): DocumentTemplateData
    {
        Gate::authorize('update', $template);

        if ($version->document_template_id !== $template->id) {
            abort(404);
        }

        return DB::transaction(function () use ($template, $version): DocumentTemplateData {
            $this->snapshotVersion($template, 'Before restore of version '.$version->version);

            $template->update([
                'body_html' => $version->body_html,
                'header_html' => $version->header_html,
                'footer_html' => $version->footer_html,
                'styles_css' => $version->styles_css,
                'page_config' => $version->page_config,
                'version' => $template->version + 1,
                'last_edited_at' => now(),
            ]);

            app(DocumentEventDispatcher::class)->template($template, 'document_template.updated');

            return DocumentTemplateData::fromModel($template->refresh());
        });
    }
}

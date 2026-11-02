<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Actions\Concerns\SnapshotsDocumentTemplateVersions;
use App\Domain\Documents\Data\DocumentTemplateData;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RestoreDefaultDocumentTemplate
{
    use SnapshotsDocumentTemplateVersions;

    public function __invoke(DocumentTemplate $template): DocumentTemplateData
    {
        Gate::authorize('update', $template);

        if (! $template->is_system) {
            throw ValidationException::withMessages([
                'document_template' => ['Only system templates can be restored to their default content.'],
            ]);
        }

        $definition = SystemDocumentTemplates::definition($template->document_type);

        return DB::transaction(function () use ($template, $definition): DocumentTemplateData {
            $this->snapshotVersion($template, 'Before restore to system default');

            $template->update([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'body_html' => $definition['body_html'],
                'header_html' => $definition['header_html'],
                'footer_html' => $definition['footer_html'],
                'styles_css' => $definition['styles_css'],
                'page_config' => $definition['page_config'],
                'branding_config' => null,
                'version' => $template->version + 1,
                'last_edited_at' => now(),
            ]);

            app(DocumentEventDispatcher::class)->template($template, 'document_template.restored');

            return DocumentTemplateData::fromModel($template->refresh());
        });
    }
}

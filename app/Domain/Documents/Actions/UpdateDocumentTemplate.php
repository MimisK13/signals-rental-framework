<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Actions\Concerns\SnapshotsDocumentTemplateVersions;
use App\Domain\Documents\Data\DocumentTemplateData;
use App\Domain\Documents\Data\UpdateDocumentTemplateData;
use App\Domain\Documents\Models\DocumentTemplate;
use App\Domain\Documents\Services\DocumentEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateDocumentTemplate
{
    use SnapshotsDocumentTemplateVersions;

    public function __invoke(DocumentTemplate $template, UpdateDocumentTemplateData $data): DocumentTemplateData
    {
        Gate::authorize('update', $template);

        if ($data->slug !== null
            && DocumentTemplate::query()
                ->where('store_id', $template->store_id)
                ->where('slug', $data->slug)
                ->whereKeyNot($template->id)
                ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken for this store.'],
            ]);
        }

        return DB::transaction(function () use ($template, $data): DocumentTemplateData {
            $this->snapshotVersion($template, $data->change_note);

            $attributes = array_filter([
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'body_html' => $data->body_html,
                'header_html' => $data->header_html,
                'footer_html' => $data->footer_html,
                'styles_css' => $data->styles_css,
                'page_config' => $data->page_config,
                'branding_config' => $data->branding_config,
                'is_active' => $data->is_active,
            ], fn (mixed $value): bool => $value !== null);

            $template->update([
                ...$attributes,
                'version' => $template->version + 1,
                'last_edited_at' => now(),
            ]);

            app(DocumentEventDispatcher::class)->template($template, 'document_template.updated');

            return DocumentTemplateData::fromModel($template->refresh());
        });
    }
}

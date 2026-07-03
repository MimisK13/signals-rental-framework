<?php

namespace App\Actions\Documents;

use App\Actions\Documents\Concerns\SnapshotsDocumentTemplateVersions;
use App\Data\Documents\DocumentTemplateData;
use App\Data\Documents\UpdateDocumentTemplateData;
use App\Models\DocumentTemplate;
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

            return DocumentTemplateData::fromModel($template->refresh());
        });
    }
}

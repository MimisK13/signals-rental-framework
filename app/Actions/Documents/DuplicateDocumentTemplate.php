<?php

namespace App\Actions\Documents;

use App\Data\Documents\DocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentEventDispatcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DuplicateDocumentTemplate
{
    public function __invoke(DocumentTemplate $template): DocumentTemplateData
    {
        Gate::authorize('update', $template);

        $baseSlug = Str::slug($template->slug.'-copy');
        $slug = $this->uniqueSlug($template->store_id, $baseSlug);
        $name = 'Copy of '.$template->name;

        $duplicate = DocumentTemplate::query()->create([
            'store_id' => $template->store_id,
            'created_by_member_id' => auth()->user()?->member_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $template->description,
            'document_type' => $template->document_type,
            'model_type' => $template->model_type,
            'body_html' => $template->body_html,
            'header_html' => $template->header_html,
            'footer_html' => $template->footer_html,
            'styles_css' => $template->styles_css,
            'page_config' => $template->page_config,
            'branding_config' => $template->branding_config,
            'is_default' => false,
            'is_system' => false,
            'is_active' => $template->is_active,
            'version' => 1,
            'last_edited_at' => now(),
        ]);

        app(DocumentEventDispatcher::class)->template($duplicate, 'document_template.created');

        return DocumentTemplateData::fromModel($duplicate);
    }

    private function uniqueSlug(int $storeId, string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (DocumentTemplate::query()->where('store_id', $storeId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

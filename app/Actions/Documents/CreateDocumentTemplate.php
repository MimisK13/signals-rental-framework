<?php

namespace App\Actions\Documents;

use App\Actions\Documents\Concerns\ValidatesDocumentTemplateStoreAccess;
use App\Data\Documents\CreateDocumentTemplateData;
use App\Data\Documents\DocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentEventDispatcher;
use App\Services\Documents\DocumentTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateDocumentTemplate
{
    use ValidatesDocumentTemplateStoreAccess;

    public function __invoke(CreateDocumentTemplateData $data): DocumentTemplateData
    {
        Gate::authorize('create', DocumentTemplate::class);
        $this->assertUserCanAccessStore($data->store_id);

        if (! app(DocumentTypeRegistry::class)->has($data->document_type)) {
            throw ValidationException::withMessages([
                'document_type' => ['The document type is not registered.'],
            ]);
        }

        if (DocumentTemplate::query()->where('store_id', $data->store_id)->where('slug', $data->slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken for this store.'],
            ]);
        }

        return DB::transaction(function () use ($data): DocumentTemplateData {
            if ($data->is_default) {
                $this->clearDefaultForType($data->store_id, $data->document_type);
            }

            $template = DocumentTemplate::query()->create([
                'store_id' => $data->store_id,
                'created_by_member_id' => auth()->user()?->member_id,
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'document_type' => $data->document_type,
                'model_type' => $data->model_type ?? app(DocumentTypeRegistry::class)->modelType($data->document_type),
                'body_html' => $data->body_html,
                'header_html' => $data->header_html,
                'footer_html' => $data->footer_html,
                'styles_css' => $data->styles_css,
                'page_config' => $data->page_config,
                'branding_config' => $data->branding_config,
                'is_default' => $data->is_default,
                'is_system' => false,
                'is_active' => $data->is_active,
                'version' => 1,
                'last_edited_at' => now(),
            ]);

            app(DocumentEventDispatcher::class)->template($template, 'document_template.created');

            return DocumentTemplateData::fromModel($template);
        });
    }

    private function clearDefaultForType(int $storeId, string $documentType): void
    {
        DocumentTemplate::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}

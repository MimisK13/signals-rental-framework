<?php

namespace App\Actions\Documents;

use App\Actions\Documents\Concerns\ValidatesDocumentTemplateStoreAccess;
use App\Data\Documents\DocumentTemplateData;
use App\Data\Documents\ImportDocumentTemplateData;
use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentEventDispatcher;
use App\Services\Documents\DocumentTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Import a portable JSON template export as a new non-system template.
 */
class ImportDocumentTemplate
{
    use ValidatesDocumentTemplateStoreAccess;

    public function __invoke(ImportDocumentTemplateData $data): DocumentTemplateData
    {
        Gate::authorize('create', DocumentTemplate::class);
        $this->assertUserCanAccessStore($data->store_id);
        $this->validatePayload($data->payload);

        $slug = $data->slug ?? $this->uniqueSlug($data->store_id, Str::slug($data->payload['name']));

        if (DocumentTemplate::query()->where('store_id', $data->store_id)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['The slug has already been taken for this store.'],
            ]);
        }

        return DB::transaction(function () use ($data, $slug): DocumentTemplateData {
            $template = DocumentTemplate::query()->create([
                'store_id' => $data->store_id,
                'created_by_member_id' => auth()->user()?->member_id,
                'name' => $data->payload['name'],
                'slug' => $slug,
                'description' => $data->payload['description'] ?? null,
                'document_type' => $data->payload['document_type'],
                'model_type' => $data->payload['model_type'],
                'body_html' => $data->payload['body_html'],
                'header_html' => $data->payload['header_html'] ?? null,
                'footer_html' => $data->payload['footer_html'] ?? null,
                'styles_css' => $data->payload['styles_css'] ?? null,
                'page_config' => $data->payload['page_config'] ?? null,
                'branding_config' => $data->payload['branding_config'] ?? null,
                'is_default' => false,
                'is_system' => false,
                'is_active' => (bool) ($data->payload['is_active'] ?? true),
                'version' => 1,
                'last_edited_at' => now(),
            ]);

            app(DocumentEventDispatcher::class)->template($template, 'document_template.created');

            return DocumentTemplateData::fromModel($template);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validatePayload(array $payload): void
    {
        $errors = [];

        if (($payload['export_format'] ?? null) !== 'signals.document_template') {
            $errors['export_format'][] = 'Unsupported export format.';
        }

        if (($payload['export_version'] ?? null) !== 1) {
            $errors['export_version'][] = 'Unsupported export version.';
        }

        foreach (['name', 'document_type', 'model_type', 'body_html'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field]) || trim($payload[$field]) === '') {
                $errors[$field][] = "The {$field} field is required.";
            }
        }

        if (isset($payload['document_type']) && is_string($payload['document_type'])
            && ! app(DocumentTypeRegistry::class)->has($payload['document_type'])) {
            $errors['document_type'][] = 'The document type is not registered.';
        }

        if (isset($payload['document_type'], $payload['model_type'])
            && is_string($payload['document_type'])
            && is_string($payload['model_type'])
            && trim($payload['model_type']) !== ''
            && app(DocumentTypeRegistry::class)->has($payload['document_type'])
            && $payload['model_type'] !== app(DocumentTypeRegistry::class)->modelType($payload['document_type'])) {
            $errors['model_type'][] = 'The model type does not match the registered source model for this document type.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function uniqueSlug(int $storeId, string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'imported-template';
        $suffix = 2;

        while (DocumentTemplate::query()->where('store_id', $storeId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

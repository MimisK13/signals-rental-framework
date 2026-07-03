<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Validation\ValidationException;

class DocumentTemplateResolver
{
    public function hasActiveTemplate(int $storeId, string $documentType): bool
    {
        return DocumentTemplate::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->exists();
    }

    public function resolveForGeneration(
        int $storeId,
        ?int $templateId,
        ?string $documentType,
    ): DocumentTemplate {
        if ($templateId !== null) {
            $template = DocumentTemplate::query()
                ->with('store')
                ->findOrFail($templateId);

            if ($template->store_id !== $storeId) {
                throw ValidationException::withMessages([
                    'document_template_id' => ['The template does not belong to the source store.'],
                ]);
            }

            return $template;
        }

        if ($documentType === null || $documentType === '') {
            throw ValidationException::withMessages([
                'document_type' => ['Either document_template_id or document_type is required.'],
            ]);
        }

        $template = DocumentTemplate::query()
            ->where('store_id', $storeId)
            ->where('document_type', $documentType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($template === null) {
            throw ValidationException::withMessages([
                'document_type' => ['No active template exists for this document type and store.'],
            ]);
        }

        return $template->loadMissing('store');
    }
}
